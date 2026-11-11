<?php

namespace Tests\Feature\Modules\FavouritesPreferences\Http;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Models\ShoppingList;
use App\Modules\FavouritesPreferences\Models\ShoppingListItem;
use App\Modules\FavouritesPreferences\Services\ShoppingListService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ShopProduct $listing;

    private ShoppingList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->listing = ShopProduct::factory()->create();
        $this->list = ShoppingList::factory()->for($this->user)->for($this->listing->shop->mall)->create(['name' => 'Family shopping']);
    }

    private function productUrl(): string
    {
        return route('products.show', ['publicMall' => $this->listing->shop->mall->slug, 'publicProduct' => $this->listing->variant->product_id]);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('account.lists.index'))->assertRedirect(route('login'));
        $this->get(route('account.lists.show', $this->list))->assertRedirect(route('login'));
        $this->post(route('account.lists.store'), ['name' => 'X'])->assertRedirect(route('login'));
    }

    public function test_a_customer_creates_renames_and_deletes_a_list(): void
    {
        $mall = Mall::factory()->create(['name' => 'Niagara City Mall']);

        $this->actingAs($this->user)->get(route('account.lists.index'))->assertOk()->assertSee('Family shopping')->assertSee('Niagara City Mall');

        $response = $this->post(route('account.lists.store'), ['name' => 'Party', 'mall_id' => $mall->getKey()]);
        $party = ShoppingList::query()->where('name', 'Party')->sole();
        $response->assertRedirect(route('account.lists.show', $party));

        $this->put(route('account.lists.update', $party), ['name' => 'Birthday party'])->assertRedirect(route('account.lists.show', $party));
        $this->assertSame('Birthday party', $party->refresh()->name);

        $this->delete(route('account.lists.destroy', $party))->assertRedirect(route('account.lists.index'));
        $this->assertModelMissing($party);
    }

    public function test_creating_a_list_needs_a_name_and_an_active_mall(): void
    {
        $this->actingAs($this->user)
            ->post(route('account.lists.store'), ['name' => '', 'mall_id' => Mall::factory()->draft()->create()->getKey()])
            ->assertSessionHasErrors(['name', 'mall_id']);
    }

    public function test_a_product_is_added_from_its_page_and_shown_on_the_list(): void
    {
        $this->actingAs($this->user)->get($this->productUrl())->assertSee('Add to "Family shopping"');

        $this->from($this->productUrl())
            ->post(route('account.lists.items.store', $this->list), ['product_id' => $this->listing->variant->product_id, 'quantity' => 2])
            ->assertRedirect($this->productUrl())
            ->assertSessionHas('status');

        $this->get(route('account.lists.show', $this->list))
            ->assertOk()
            ->assertSee($this->listing->variant->product->name)
            ->assertSee('value="2"', false)
            ->assertSee('href="'.$this->productUrl().'"', false);
    }

    public function test_free_text_lines_are_ticked_off_changed_and_removed(): void
    {
        $this->actingAs($this->user)->post(route('account.lists.items.store', $this->list), ['label' => 'Birthday candles', 'note' => 'blue']);
        $item = ShoppingListItem::query()->sole();

        $this->patch(route('account.lists.items.update', [$this->list, $item]), ['is_done' => '1'])->assertRedirect(route('account.lists.show', $this->list));
        $this->patch(route('account.lists.items.update', [$this->list, $item]), ['quantity' => 3]);
        $this->assertTrue($item->refresh()->is_done);
        $this->assertSame(3, $item->quantity);
        $this->assertSame('blue', $item->note);

        $this->get(route('account.lists.show', $this->list))->assertSee('Mark Birthday candles as not bought');

        $this->delete(route('account.lists.items.destroy', [$this->list, $item]));
        $this->assertModelMissing($item);
    }

    public function test_invalid_lines_are_refused(): void
    {
        $this->actingAs($this->user);

        $this->post(route('account.lists.items.store', $this->list), ['label' => ''])->assertSessionHasErrors('label');
        $this->post(route('account.lists.items.store', $this->list), ['label' => 'Milk', 'quantity' => 100])->assertSessionHasErrors('quantity');
        $this->post(route('account.lists.items.store', $this->list), ['product_id' => ShopProduct::factory()->archived()->create()->variant->product_id])->assertSessionHasErrors();
        $this->assertSame(0, ShoppingListItem::query()->count());
    }

    public function test_a_product_sold_only_in_another_mall_is_refused_with_a_message(): void
    {
        $elsewhere = ShopProduct::factory()->create()->variant;

        $this->actingAs($this->user)
            ->from(route('account.lists.show', $this->list))
            ->post(route('account.lists.items.store', $this->list), ['product_id' => $elsewhere->product_id])
            ->assertRedirect(route('account.lists.show', $this->list))
            ->assertSessionHasErrors('business');
    }

    public function test_another_accounts_list_answers_not_found(): void
    {
        $item = app(ShoppingListService::class)->addCustomItem($this->list, 'Private item');
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('account.lists.show', $this->list))->assertNotFound();
        $this->put(route('account.lists.update', $this->list), ['name' => 'Hacked'])->assertNotFound();
        $this->delete(route('account.lists.destroy', $this->list))->assertNotFound();
        $this->post(route('account.lists.items.store', $this->list), ['label' => 'Spam'])->assertNotFound();
        $this->patch(route('account.lists.items.update', [$this->list, $item]), ['is_done' => '1'])->assertNotFound();
        $this->delete(route('account.lists.items.destroy', [$this->list, $item]))->assertNotFound();
        $this->get(route('account.lists.index'))->assertDontSee('Family shopping');

        $this->assertSame('Family shopping', $this->list->refresh()->name);
        $this->assertFalse($item->refresh()->is_done);
    }

    public function test_an_item_of_one_list_cannot_be_changed_through_another(): void
    {
        $item = app(ShoppingListService::class)->addCustomItem($this->list, 'Milk');
        $otherList = ShoppingList::factory()->for($this->user)->for($this->listing->shop->mall)->create();

        $this->actingAs($this->user)->delete(route('account.lists.items.destroy', [$otherList, $item]))->assertNotFound();
        $this->assertModelExists($item);
    }
}
