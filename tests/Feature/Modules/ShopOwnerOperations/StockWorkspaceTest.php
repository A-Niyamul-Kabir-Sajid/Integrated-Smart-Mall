<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ListingStock;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Models\StockMovement;
use App\Modules\ShopOwnerOperations\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private ShopProduct $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->listing = ShopProduct::factory()->for($this->shop)->create(['seller_sku' => 'ST-001']);
    }

    private function as(ShopFunction $function): User
    {
        $user = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($user, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => $function]);

        return $user;
    }

    public function test_stock_screens_are_closed_to_guests_cashiers_unassigned_staff_and_other_shops(): void
    {
        $unassigned = User::factory()->create();
        app(MembershipService::class)->grantShopRole($unassigned, $this->shop, RoleCode::ShopStaff);

        $this->get(route('workspace.stock.index', $this->shop))->assertRedirect(route('login'));

        foreach ([$this->as(ShopFunction::Cashier), $unassigned, User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('workspace.stock.index', $this->shop))->assertForbidden();
            $this->post(route('workspace.stock.receive', [$this->shop, $this->listing]), ['quantity' => 5])->assertForbidden();
        }

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_the_catalogue_editor_receives_counts_and_sets_the_alert_level(): void
    {
        $editor = $this->as(ShopFunction::CatalogueEditor);
        $this->actingAs($editor)->get(route('workspace.stock.index', $this->shop))->assertOk()->assertSee('ST-001')->assertSee('Not tracked');

        $this->get(route('workspace.stock.show', [$this->shop, $this->listing]))->assertOk()->assertSee('Not tracked yet');
        $this->post(route('workspace.stock.receive', [$this->shop, $this->listing]), ['quantity' => 12, 'reason' => 'Invoice 1'])->assertRedirect()->assertSessionHas('status');
        $this->post(route('workspace.stock.adjust', [$this->shop, $this->listing]), ['counted' => 9, 'reason' => 'Stocktake'])->assertRedirect();
        $this->put(route('workspace.stock.threshold', [$this->shop, $this->listing]), ['threshold' => 10])->assertRedirect();

        $this->assertSame(9, ListingStock::query()->sole()->on_hand);
        $this->get(route('workspace.stock.show', [$this->shop, $this->listing]))
            ->assertSee('On hand: 9')->assertSee('Low stock')->assertSee('Invoice 1')->assertSee('Stocktake')->assertSee($editor->display_name);
        $this->get(route('workspace.stock.index', [$this->shop, 'low' => 1]))->assertSee('ST-001');
        $this->get(route('workspace.catalogue.index', $this->shop))->assertSee('Low');
    }

    public function test_invalid_input_is_refused_and_nothing_is_saved(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('workspace.stock.receive', [$this->shop, $this->listing]), ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->post(route('workspace.stock.receive', [$this->shop, $this->listing]), ['quantity' => 'abc'])->assertSessionHasErrors('quantity');
        $this->post(route('workspace.stock.adjust', [$this->shop, $this->listing]), ['counted' => 3])->assertSessionHasErrors('reason');
        $this->post(route('workspace.stock.adjust', [$this->shop, $this->listing]), ['counted' => -1, 'reason' => 'x'])->assertSessionHasErrors('counted');
        $this->post(route('workspace.stock.adjust', [$this->shop, $this->listing]), ['counted' => 0, 'reason' => 'nothing to change'])->assertSessionHasErrors('business');

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_a_listing_of_another_shop_is_not_found_through_this_shop(): void
    {
        $foreign = ShopProduct::factory()->for(Shop::factory()->create())->create();

        $this->actingAs($this->owner)->get(route('workspace.stock.show', [$this->shop, $foreign]))->assertNotFound();
        $this->post(route('workspace.stock.receive', [$this->shop, $foreign]), ['quantity' => 5])->assertNotFound();
        $this->put(route('workspace.stock.threshold', [$this->shop, $foreign]), ['threshold' => 5])->assertNotFound();
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_the_workspace_home_flags_low_stock_for_those_who_manage_stock(): void
    {
        app(StockService::class)->receive($this->owner, $this->listing, 2);
        app(StockService::class)->setThreshold($this->owner, $this->listing, 5);

        $this->actingAs($this->owner)->get(route('workspace.dashboard', $this->shop))->assertSee('1 item low');
        $this->actingAs($this->as(ShopFunction::Cashier))->get(route('workspace.dashboard', $this->shop))->assertDontSee('item low');
    }

    public function test_the_shop_only_sees_its_own_stock_rows(): void
    {
        $other = Shop::factory()->create();
        $foreign = ShopProduct::factory()->for($other)->create(['seller_sku' => 'OTHER-1']);
        $ownerOfOther = User::factory()->create();
        app(MembershipService::class)->grantShopRole($ownerOfOther, $other, RoleCode::ShopOwner);
        app(StockService::class)->receive($ownerOfOther, $foreign, 4);

        $this->actingAs($this->owner)->get(route('workspace.stock.index', $this->shop))->assertDontSee('OTHER-1');
        $this->assertCount(0, app(StockService::class)->reconcile($this->shop));
    }

    public function test_an_owner_adds_another_option_to_a_product_they_alone_list(): void
    {
        $product = Product::factory()->create(['name' => 'Cold Coffee']);
        $variant = ProductVariant::factory()->for($product)->create(['variant_code' => 'regular', 'label' => 'Regular']);
        $listing = ShopProduct::factory()->for($this->shop)->create(['variant_id' => $variant->getKey(), 'seller_sku' => 'CC-R']);

        $this->actingAs($this->owner)->get(route('workspace.catalogue.edit', [$this->shop, $listing]))->assertSee('Add another option of this product');
        $this->post(route('workspace.catalogue.variants.store', [$this->shop, $listing]), [
            'variant_label' => 'Large', 'variant_code' => 'large', 'seller_sku' => 'CC-L', 'price_amount' => '220', 'availability_status' => 'available',
        ])->assertRedirect(route('workspace.catalogue.index', $this->shop));

        $this->assertSame(2, $product->variants()->count());
        $this->assertTrue(ShopProduct::query()->where('shop_id', $this->shop->getKey())->where('seller_sku', 'CC-L')->exists());

        $this->post(route('workspace.catalogue.variants.store', [$this->shop, $listing]), [
            'variant_label' => 'Large', 'variant_code' => 'large', 'seller_sku' => 'CC-L2', 'price_amount' => '220', 'availability_status' => 'available',
        ])->assertSessionHasErrors('business');
        $this->post(route('workspace.catalogue.variants.store', [$this->shop, $listing]), ['variant_label' => '', 'seller_sku' => 'X'])->assertSessionHasErrors('variant_label');
    }

    public function test_another_option_is_refused_when_other_shops_also_list_the_product(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $listing = ShopProduct::factory()->for($this->shop)->create(['variant_id' => $variant->getKey()]);
        ShopProduct::factory()->for(Shop::factory()->create())->create(['variant_id' => $variant->getKey()]);

        $this->actingAs($this->owner)->post(route('workspace.catalogue.variants.store', [$this->shop, $listing]), [
            'variant_label' => 'Large', 'seller_sku' => 'NEW-1', 'price_amount' => '10', 'availability_status' => 'available',
        ])->assertSessionHasErrors('business');
        $this->assertSame(1, $product->variants()->count());
    }
}
