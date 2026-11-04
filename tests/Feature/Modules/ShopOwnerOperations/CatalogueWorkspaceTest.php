<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Cafe Brew']);
        $this->staff = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->staff, $this->shop, RoleCode::ShopStaff);
    }

    private function listingInput(array $overrides = []): array
    {
        return ['seller_sku' => 'CB-010', 'price_amount' => '150', 'availability_status' => 'available', ...$overrides];
    }

    public function test_staff_see_their_shops_catalogue(): void
    {
        ShopProduct::factory()->for($this->shop)->create(['seller_sku' => 'CB-001']);
        ShopProduct::factory()->for($this->shop)->archived()->create(['seller_sku' => 'CB-OLD']);

        $this->actingAs($this->staff)
            ->get(route('workspace.catalogue.index', $this->shop))
            ->assertOk()
            ->assertSee('CB-001')
            ->assertDontSee('CB-OLD');

        $this->actingAs($this->staff)->get(route('workspace.catalogue.index', [$this->shop, 'status' => 'archived']))->assertSee('CB-OLD');
    }

    public function test_guests_customers_and_other_shops_staff_are_kept_out(): void
    {
        $outsider = User::factory()->create();
        app(MembershipService::class)->grantShopRole($outsider, Shop::factory()->create(), RoleCode::ShopOwner);

        $this->get(route('workspace.catalogue.index', $this->shop))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('workspace.catalogue.index', $this->shop))->assertForbidden();
        $this->actingAs($outsider)->get(route('workspace.catalogue.index', $this->shop))->assertForbidden();
        $this->actingAs($outsider)->post(route('workspace.catalogue.store', $this->shop), ['mode' => 'existing'])->assertForbidden();
    }

    public function test_a_listing_of_another_shop_is_not_found_under_this_shops_url(): void
    {
        $foreign = ShopProduct::factory()->create();

        $this->actingAs($this->staff)->get(route('workspace.catalogue.edit', [$this->shop, $foreign]))->assertNotFound();
        $this->actingAs($this->staff)->get(route('workspace.catalogue.edit', [$this->shop, 'not-a-uuid']))->assertNotFound();
        $this->actingAs($this->staff)->post(route('workspace.catalogue.archive', [$this->shop, $foreign]))->assertNotFound();
        $this->assertSame('active', $foreign->fresh()->status);
    }

    public function test_staff_create_a_new_product_listing(): void
    {
        $category = ProductCategory::factory()->create();

        $this->actingAs($this->staff)
            ->post(route('workspace.catalogue.store', $this->shop), [
                'mode' => 'new', 'name' => 'Cold Coffee', 'brand' => 'Cafe Brew', 'product_category_id' => $category->getKey(),
                'variant_label' => 'Large', 'variant_code' => 'large', ...$this->listingInput(['price_amount' => '280.50']),
            ])
            ->assertRedirect(route('workspace.catalogue.index', $this->shop));

        $listing = ShopProduct::query()->sole();
        $this->assertSame([$this->shop->getKey(), '280.50', 'large'], [$listing->shop_id, $listing->price_amount, $listing->variant->variant_code]);
    }

    public function test_staff_list_an_existing_shared_product(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->actingAs($this->staff)->get(route('workspace.catalogue.create', $this->shop))->assertSee($variant->variant_id);

        $this->actingAs($this->staff)
            ->post(route('workspace.catalogue.store', $this->shop), ['mode' => 'existing', 'variant_id' => $variant->getKey(), ...$this->listingInput()])
            ->assertRedirect();

        $this->assertSame(1, ShopProduct::query()->where('shop_id', $this->shop->getKey())->count());
    }

    public function test_invalid_listing_input_and_business_rules_are_reported(): void
    {
        ShopProduct::factory()->for($this->shop)->create(['seller_sku' => 'CB-010']);
        $variant = ProductVariant::factory()->create();

        $this->actingAs($this->staff)
            ->post(route('workspace.catalogue.store', $this->shop), ['mode' => 'existing', 'variant_id' => $variant->getKey(), 'seller_sku' => 'bad sku!', 'price_amount' => '-1', 'availability_status' => 'maybe'])
            ->assertSessionHasErrors(['seller_sku' => 'Use letters, digits, dots, dashes or underscores in the SKU.', 'price_amount', 'availability_status']);

        $this->actingAs($this->staff)
            ->from(route('workspace.catalogue.create', $this->shop))
            ->post(route('workspace.catalogue.store', $this->shop), ['mode' => 'existing', 'variant_id' => $variant->getKey(), ...$this->listingInput()])
            ->assertRedirect(route('workspace.catalogue.create', $this->shop))
            ->assertSessionHasErrors(['business' => 'Your shop already uses the SKU CB-010.']);
    }

    public function test_staff_update_and_archive_their_listing(): void
    {
        $listing = ShopProduct::factory()->for($this->shop)->create(['seller_sku' => 'CB-001', 'price_amount' => 100]);

        $this->actingAs($this->staff)->put(route('workspace.catalogue.update', [$this->shop, $listing]), $this->listingInput(['seller_sku' => 'CB-001', 'price_amount' => '95.5', 'availability_status' => 'unavailable']))->assertRedirect();
        $this->actingAs($this->staff)->post(route('workspace.catalogue.archive', [$this->shop, $listing]))->assertRedirect(route('workspace.catalogue.index', $this->shop));

        $this->assertSame(['95.50', 'unavailable', 'archived'], [$listing->fresh()->price_amount, $listing->fresh()->availability_status, $listing->fresh()->status]);
    }

    public function test_shared_details_are_editable_only_by_the_sole_lister(): void
    {
        Storage::fake('public');
        $listing = ShopProduct::factory()->for($this->shop)->create();
        $product = $listing->variant->product;

        $this->actingAs($this->staff)->get(route('workspace.catalogue.edit', [$this->shop, $listing]))
            ->assertSee('Save product details')
            ->assertSee('<label for="description"', false);
        $this->actingAs($this->staff)
            ->put(route('workspace.catalogue.product.update', [$this->shop, $listing]), ['name' => 'Better Name', 'product_category_id' => $product->product_category_id])
            ->assertRedirect();
        $this->actingAs($this->staff)
            ->post(route('workspace.catalogue.images.store', [$this->shop, $listing]), ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), 'alt_text' => 'x'])
            ->assertSessionHasErrors('image');
        $this->assertSame('Better Name', $product->fresh()->name);

        ShopProduct::factory()->for($listing->variant, 'variant')->create();

        $this->actingAs($this->staff)->get(route('workspace.catalogue.edit', [$this->shop, $listing]))->assertDontSee('Save product details');
        $this->actingAs($this->staff)
            ->put(route('workspace.catalogue.product.update', [$this->shop, $listing]), ['name' => 'Hijacked', 'product_category_id' => $product->product_category_id])
            ->assertSessionHasErrors('business');
        $this->assertSame('Better Name', $product->fresh()->name);
    }

    public function test_the_account_page_links_staff_to_their_shop_workspace(): void
    {
        $this->actingAs($this->staff)
            ->get(route('account.show'))
            ->assertSee('Cafe Brew')
            ->assertSee('href="'.route('workspace.catalogue.index', $this->shop).'"', false);

        $this->actingAs(User::factory()->create())->get(route('account.show'))->assertDontSee('Shops you work in');
    }
}
