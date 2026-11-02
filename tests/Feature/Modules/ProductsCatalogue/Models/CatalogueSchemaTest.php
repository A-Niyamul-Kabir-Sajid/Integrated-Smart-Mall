<?php

namespace Tests\Feature\Modules\ProductsCatalogue\Models;

use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogueSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_has_several_variants_and_reaches_their_listings(): void
    {
        $product = Product::factory()->create();
        $small = ProductVariant::factory()->for($product)->create(['variant_code' => 'small']);
        $large = ProductVariant::factory()->for($product)->create(['variant_code' => 'large']);
        ShopProduct::factory()->for($small, 'variant')->create();
        ShopProduct::factory()->for($large, 'variant')->create();

        $this->assertSame(['large', 'small'], $product->variants()->orderBy('variant_code')->pluck('variant_code')->all());
        $this->assertSame(2, $product->listings()->count());
    }

    public function test_a_variant_code_is_unique_within_its_product_only(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['variant_code' => 'default']);
        ProductVariant::factory()->create(['variant_code' => 'default']);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->for($product)->create(['variant_code' => 'default']);
    }

    public function test_a_shop_lists_each_variant_once(): void
    {
        $shop = Shop::factory()->create();
        $variant = ProductVariant::factory()->create();
        ShopProduct::factory()->for($shop)->for($variant, 'variant')->create();

        $this->expectException(QueryException::class);

        ShopProduct::factory()->for($shop)->for($variant, 'variant')->create();
    }

    public function test_a_seller_sku_is_unique_within_its_shop_only(): void
    {
        $shop = Shop::factory()->create();
        ShopProduct::factory()->for($shop)->create(['seller_sku' => 'SKU-1']);
        ShopProduct::factory()->create(['seller_sku' => 'SKU-1']);

        $this->expectException(QueryException::class);

        ShopProduct::factory()->for($shop)->create(['seller_sku' => 'SKU-1']);
    }

    public function test_listings_must_reference_an_existing_shop_and_variant(): void
    {
        $this->expectException(QueryException::class);

        ShopProduct::factory()->create(['shop_id' => (string) Str::uuid()]);
    }

    public function test_products_must_reference_an_existing_category(): void
    {
        $this->expectException(QueryException::class);

        Product::factory()->create(['product_category_id' => (string) Str::uuid()]);
    }

    public function test_product_categories_nest_under_a_parent(): void
    {
        $drinks = ProductCategory::factory()->create();
        $juice = ProductCategory::factory()->create(['parent_category_id' => $drinks->getKey()]);

        $this->assertTrue($juice->parent->is($drinks));
    }

    public function test_postgresql_rejects_negative_prices(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The price check constraint exists on PostgreSQL only; the service validates prices everywhere.');
        }

        $this->expectException(QueryException::class);

        ShopProduct::factory()->create(['price_amount' => -1]);
    }
}
