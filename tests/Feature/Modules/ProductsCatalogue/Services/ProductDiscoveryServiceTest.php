<?php

namespace Tests\Feature\Modules\ProductsCatalogue\Services;

use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Shop $fresh;

    private Shop $corner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->fresh = Shop::factory()->for($this->mall)->create(['slug' => 'fresh-mart']);
        $this->corner = Shop::factory()->for($this->mall)->create(['slug' => 'juice-corner']);
    }

    private function offer(string $name, Shop $shop, float $price, array $listing = [], array $product = [], ?ProductCategory $category = null): ShopProduct
    {
        $existing = Product::query()->where('name', $name)->first();
        $productModel = $existing ?? Product::factory()->create(['name' => $name, 'brand' => null, 'product_category_id' => ($category ?? ProductCategory::factory()->create())->getKey(), ...$product]);
        $variant = ProductVariant::factory()->for($productModel)->create(['variant_code' => uniqid('v', true)]);

        return ShopProduct::factory()->for($shop)->for($variant, 'variant')->create(['price_amount' => $price, ...$listing]);
    }

    private function names(array $filters = []): array
    {
        return app(CatalogueLookup::class)->searchProducts($this->mall, $filters)->pluck('name')->all();
    }

    public function test_only_offers_customers_can_buy_make_a_product_visible(): void
    {
        $this->offer('Visible Water', $this->fresh, 20);
        $this->offer('Archived Listing', $this->fresh, 20, ['status' => 'archived']);
        $this->offer('Archived Product', $this->fresh, 20, product: ['status' => 'archived']);
        $this->offer('Closed Shop Item', Shop::factory()->for($this->mall)->closed()->create(), 20);
        $this->offer('Draft Shop Item', Shop::factory()->for($this->mall)->draft()->create(), 20);
        $this->offer('Other Mall Item', Shop::factory()->create(), 20);
        $archivedVariant = $this->offer('Archived Option', $this->fresh, 20);
        $archivedVariant->variant->update(['status' => 'archived']);

        $this->assertSame(['Visible Water'], $this->names());
    }

    public function test_reports_the_cheapest_matching_offer_and_how_many_shops_sell_it(): void
    {
        $this->offer('Mineral Water', $this->fresh, 20);
        $this->offer('Mineral Water', $this->corner, 25);

        $product = app(CatalogueLookup::class)->searchProducts($this->mall)->sole();

        $this->assertSame([20.0, 2], [(float) $product->min_price, $product->offer_count]);
        $this->assertSame(25.0, (float) app(CatalogueLookup::class)->searchProducts($this->mall, ['shop' => 'juice-corner'])->sole()->min_price);
    }

    public function test_filters_by_keyword_category_shop_price_and_availability(): void
    {
        $drinks = ProductCategory::factory()->create(['slug' => 'drinks', 'name' => 'Drinks']);
        $this->offer('Mango Juice', $this->corner, 120, category: $drinks, product: ['brand' => 'Sonali']);
        $this->offer('Cola', $this->fresh, 40, ['availability_status' => 'unavailable'], category: $drinks);
        $this->offer('USB Charger', $this->fresh, 1450);

        $this->assertSame(['Mango Juice'], $this->names(['q' => 'SONALI']));
        $this->assertSame(['Cola', 'Mango Juice'], $this->names(['q' => 'drinks']));
        $this->assertSame(['Cola', 'Mango Juice'], $this->names(['category' => 'drinks']));
        $this->assertSame(['Cola', 'USB Charger'], $this->names(['shop' => 'fresh-mart']));
        $this->assertSame(['Cola', 'Mango Juice'], $this->names(['max_price' => '500']));
        $this->assertSame(['USB Charger'], $this->names(['min_price' => '1000']));
        $this->assertSame(['Mango Juice', 'USB Charger'], $this->names(['available_only' => true]));
        $this->assertSame([], $this->names(['q' => '%']));
    }

    public function test_sorts_by_name_or_cheapest_price_and_paginates(): void
    {
        $this->offer('Bread', $this->fresh, 50);
        $this->offer('Apple', $this->fresh, 300);
        $this->offer('Cheese', $this->fresh, 10);

        $this->assertSame(['Apple', 'Bread', 'Cheese'], $this->names());
        $this->assertSame(['Cheese', 'Bread', 'Apple'], $this->names(['sort' => 'price_asc']));
        $this->assertSame(['Apple', 'Bread', 'Cheese'], $this->names(['sort' => 'price_desc']));
        $this->assertSame(2, app(CatalogueLookup::class)->searchProducts($this->mall, [], perPage: 2)->lastPage());
    }

    public function test_offers_for_a_product_are_its_visible_listings_cheapest_first(): void
    {
        $this->offer('Mineral Water', $this->corner, 25);
        $this->offer('Mineral Water', $this->fresh, 20);
        $this->offer('Mineral Water', Shop::factory()->for($this->mall)->closed()->create(), 5);

        $offers = app(CatalogueLookup::class)->offersFor($this->mall, Product::query()->sole());

        $this->assertSame(['fresh-mart', 'juice-corner'], $offers->pluck('shop.slug')->all());
        $this->assertTrue($offers->first()->shop->relationLoaded('units'));
    }

    public function test_visible_listing_returns_only_buyable_offers_in_this_mall(): void
    {
        $visible = $this->offer('Water', $this->fresh, 20);
        $archived = $this->offer('Juice', $this->fresh, 20, ['status' => 'archived']);
        $elsewhere = $this->offer('Cola', Shop::factory()->create(), 20);

        $this->assertTrue(app(CatalogueLookup::class)->visibleListing($this->mall, $visible->getKey())->is($visible));
        $this->assertNull(app(CatalogueLookup::class)->visibleListing($this->mall, $archived->getKey()));
        $this->assertNull(app(CatalogueLookup::class)->visibleListing($this->mall, $elsewhere->getKey()));
    }
}
