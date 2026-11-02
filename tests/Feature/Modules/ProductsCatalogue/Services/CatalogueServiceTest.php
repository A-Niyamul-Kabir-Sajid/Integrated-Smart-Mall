<?php

namespace Tests\Feature\Modules\ProductsCatalogue\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ProductsCatalogue\Services\CatalogueService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CatalogueService
    {
        return app(CatalogueService::class);
    }

    private function listing(array $overrides = []): array
    {
        return ['seller_sku' => 'CB-001', 'price_amount' => '220.00', 'availability_status' => 'available', ...$overrides];
    }

    private function assertRuleViolated(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }

    public function test_creates_a_shared_product_variant_and_the_shops_listing(): void
    {
        $shop = Shop::factory()->create();
        $category = ProductCategory::factory()->create();

        $listing = $this->service()->createProductListing(
            $shop,
            ['product_category_id' => $category->getKey(), 'name' => ' Cold Coffee ', 'brand' => '', 'description' => 'Iced.'],
            ['variant_code' => 'Large Cup', 'label' => 'Large', 'attributes' => ['size' => 'Large']],
            $this->listing(),
        );

        $this->assertSame($shop->getKey(), $listing->shop_id);
        $this->assertSame('BDT', $listing->currency);
        $this->assertSame('large-cup', $listing->variant->variant_code);
        $this->assertSame(['size' => 'Large'], $listing->variant->attributes_json);
        $this->assertSame('Cold Coffee', $listing->variant->product->name);
        $this->assertNull($listing->variant->product->brand);
        $this->assertEqualsCanonicalizing(['product.created', 'listing.created'], AuditLog::query()->pluck('action')->all());
    }

    public function test_a_second_shop_lists_the_same_shared_variant_at_its_own_price(): void
    {
        $variant = ProductVariant::factory()->create();
        ShopProduct::factory()->for($variant, 'variant')->create(['price_amount' => 20]);

        $listing = $this->service()->listExistingVariant(Shop::factory()->create(), $variant, $this->listing(['price_amount' => '25']));

        $this->assertSame('25.00', $listing->fresh()->price_amount);
        $this->assertSame(2, $variant->listings()->count());
    }

    public function test_rejects_inactive_shops_duplicate_listings_and_skus_and_bad_prices(): void
    {
        $shop = Shop::factory()->create();
        $variant = ProductVariant::factory()->create();
        ShopProduct::factory()->for($shop)->create(['seller_sku' => 'CB-001']);

        $this->assertRuleViolated('listing.shop_not_operational', fn () => $this->service()->listExistingVariant(Shop::factory()->draft()->create(), $variant, $this->listing()));
        $this->assertRuleViolated('listing.sku_taken', fn () => $this->service()->listExistingVariant($shop, $variant, $this->listing()));
        $this->assertRuleViolated('listing.invalid_price', fn () => $this->service()->listExistingVariant($shop, $variant, $this->listing(['seller_sku' => 'CB-002', 'price_amount' => '-5'])));

        $this->service()->listExistingVariant($shop, $variant, $this->listing(['seller_sku' => 'CB-002']));
        $this->assertRuleViolated('listing.already_listed', fn () => $this->service()->listExistingVariant($shop, $variant, $this->listing(['seller_sku' => 'CB-003'])));
        $this->assertRuleViolated('listing.variant_archived', fn () => $this->service()->listExistingVariant($shop, ProductVariant::factory()->create(['status' => 'archived']), $this->listing(['seller_sku' => 'CB-004'])));
    }

    public function test_updating_a_listing_audits_only_real_price_changes(): void
    {
        $listing = ShopProduct::factory()->create(['seller_sku' => 'A-1', 'price_amount' => 100]);

        $this->service()->updateListing($listing, ['seller_sku' => 'A-1', 'price_amount' => '100.00', 'availability_status' => 'unavailable']);
        $this->service()->updateListing($listing, ['seller_sku' => 'A-2', 'price_amount' => '120', 'availability_status' => 'unavailable']);

        $this->assertSame(['A-2', '120.00', 'unavailable'], [$listing->fresh()->seller_sku, $listing->fresh()->price_amount, $listing->fresh()->availability_status]);
        $this->assertSame([['from' => '100.00', 'to' => '120.00']], AuditLog::query()->where('action', 'listing.price_changed')->pluck('metadata')->all());
    }

    public function test_archives_and_restores_a_listing_but_only_restores_for_active_shops(): void
    {
        $listing = ShopProduct::factory()->create();
        $this->service()->archiveListing($listing);
        $this->assertSame('archived', $listing->fresh()->status);

        $this->service()->restoreListing($listing);
        $this->assertSame('active', $listing->fresh()->status);

        $closedShopListing = ShopProduct::factory()->for(Shop::factory()->closed()->create())->create(['status' => 'archived']);
        $this->assertRuleViolated('listing.shop_not_operational', fn () => $this->service()->restoreListing($closedShopListing));
    }

    public function test_only_the_sole_lister_edits_shared_details_and_images(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create();
        $variant = ProductVariant::factory()->create();
        $product = $variant->product;
        ShopProduct::factory()->for($shop)->for($variant, 'variant')->create();

        $this->service()->updateSharedProduct($shop, $product, ['product_category_id' => $product->product_category_id, 'name' => 'Renamed', 'brand' => 'Brand', 'description' => null]);
        $image = $this->service()->addImage($shop, $product, UploadedFile::fake()->image('front.jpg'), 'Front of the pack');
        $this->assertSame('Renamed', $product->fresh()->name);
        $this->assertSame(1, $image->sort_order);
        Storage::disk('public')->assertExists($image->object_key);

        ShopProduct::factory()->for($variant, 'variant')->create();

        $this->assertFalse($this->service()->canEditSharedProduct($shop, $product));
        $this->assertRuleViolated('product.shared_by_other_shops', fn () => $this->service()->updateSharedProduct($shop, $product, ['product_category_id' => $product->product_category_id, 'name' => 'Hijacked']));
        $this->assertRuleViolated('product.shared_by_other_shops', fn () => $this->service()->addImage($shop, $product, UploadedFile::fake()->image('x.jpg'), 'x'));
        $this->assertSame('Renamed', Product::query()->find($product->getKey())->name);
    }
}
