<?php

namespace Tests\Feature\Modules\FavouritesPreferences\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Models\ShoppingList;
use App\Modules\FavouritesPreferences\Models\ShoppingListItem;
use App\Modules\FavouritesPreferences\Services\ShoppingListService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShoppingListService $lists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lists = app(ShoppingListService::class);
    }

    /**
     * A product with one visible offer and a list in the offer's mall.
     *
     * @return array{0: ShoppingList, 1: Product, 2: ShopProduct}
     */
    private function listWithSoldProduct(): array
    {
        $listing = ShopProduct::factory()->create();
        $list = ShoppingList::factory()->for($listing->shop->mall)->create();

        return [$list, $listing->variant->product, $listing];
    }

    private function assertRule(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }

    public function test_creating_lists_is_limited_per_account_and_needs_an_active_mall(): void
    {
        $user = User::factory()->create();
        $mall = Mall::factory()->create();
        ShoppingList::factory()->count(ShoppingListService::MAX_LISTS - 1)->for($user)->for($mall)->create();

        $this->assertSame('Party', $this->lists->create($user->getKey(), $mall, '  Party ')->name);
        $this->assertRule('shopping_list.limit_reached', fn () => $this->lists->create($user->getKey(), $mall, 'One too many'));
        $this->assertRule('shopping_list.mall_not_active', fn () => $this->lists->create(User::factory()->create()->getKey(), Mall::factory()->draft()->create(), 'Draft'));
    }

    public function test_lists_are_found_only_by_their_owner(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $list = ShoppingList::factory()->for($owner)->create();

        $this->assertNotNull($this->lists->findOwned($owner->getKey(), $list->getKey()));
        $this->assertNull($this->lists->findOwned($other->getKey(), $list->getKey()));
        $this->assertSame([], $this->lists->listsFor($other->getKey())->all());
    }

    public function test_adding_a_product_twice_raises_the_quantity(): void
    {
        [$list, $product] = $this->listWithSoldProduct();

        $first = $this->lists->addProduct($list, $product, 2);
        $second = $this->lists->addProduct($list, $product, 3, 'cold ones');

        $this->assertTrue($first->is($second));
        $this->assertSame(5, $second->refresh()->quantity);
        $this->assertSame('cold ones', $second->note);
        $this->assertSame($product->name, $second->label);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_quantity_never_exceeds_the_maximum(): void
    {
        [$list, $product] = $this->listWithSoldProduct();

        $this->lists->addProduct($list, $product, 90);

        $this->assertSame(ShoppingListService::MAX_QUANTITY, $this->lists->addProduct($list, $product, 50)->quantity);
    }

    public function test_a_product_the_mall_does_not_sell_is_refused(): void
    {
        [$list] = $this->listWithSoldProduct();
        $elsewhere = ShopProduct::factory()->create()->variant->product;

        $this->assertRule('shopping_list.product_not_sold_here', fn () => $this->lists->addProduct($list, $elsewhere));
    }

    public function test_a_list_holds_at_most_the_item_limit(): void
    {
        [$list] = $this->listWithSoldProduct();

        foreach (range(1, ShoppingListService::MAX_ITEMS) as $number) {
            $this->lists->addCustomItem($list, "Item {$number}");
        }

        $this->assertRule('shopping_list.item_limit_reached', fn () => $this->lists->addCustomItem($list, 'One too many'));
    }

    public function test_archived_products_keep_their_line_and_are_flagged(): void
    {
        [$list, $product] = $this->listWithSoldProduct();
        $this->lists->addProduct($list, $product);
        $this->lists->addCustomItem($list, 'Birthday candles');

        $product->update(['status' => 'archived']);
        $items = $this->lists->itemsOf($list->refresh());

        $this->assertCount(2, $items);
        $this->assertSame($product->name, $items->firstWhere('product_id', $product->getKey())->label);
        $this->assertFalse($items->firstWhere('product_id', $product->getKey())->is_sold_here);
        $this->assertFalse($items->firstWhere('label', 'Birthday candles')->is_sold_here);
    }

    public function test_items_are_updated_and_done_ones_sort_last(): void
    {
        [$list, $product] = $this->listWithSoldProduct();
        $milk = $this->lists->addProduct($list, $product);
        $this->lists->addCustomItem($list, 'Candles');

        $this->lists->updateItem($milk, ['is_done' => true, 'quantity' => 4, 'note' => 'two litres', 'label' => 'not changeable']);

        $this->assertTrue($milk->refresh()->is_done);
        $this->assertSame(4, $milk->quantity);
        $this->assertSame($product->name, $milk->label);
        $this->assertSame(['Candles', $product->name], $this->lists->itemsOf($list)->pluck('label')->all());
    }

    public function test_deleting_a_list_deletes_its_items(): void
    {
        [$list, $product] = $this->listWithSoldProduct();
        $this->lists->addProduct($list, $product);

        $this->lists->delete($list);

        $this->assertSame(0, ShoppingListItem::query()->count());
    }
}
