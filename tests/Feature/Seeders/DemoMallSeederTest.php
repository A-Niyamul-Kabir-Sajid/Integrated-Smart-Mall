<?php

namespace Tests\Feature\Seeders;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Database\Seeders\DemoMallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMallSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_one_active_demo_mall(): void
    {
        $this->seed(DemoMallSeeder::class);

        $mall = Mall::query()->sole();
        $this->assertSame(DemoMallSeeder::MALL_SLUG, $mall->slug);
        $this->assertSame(MallStatus::Active, $mall->status);
        $this->assertSame('Asia/Dhaka', $mall->timezone);
        $this->assertSame(['B1', 'G', 'F1'], $mall->floors()->pluck('code')->all());
        $this->assertSame(11, Facility::query()->count());
        $this->assertSame(1, Facility::query()->where('status', 'temporarily_closed')->count());
        $this->assertEqualsCanonicalizing(array_keys(DemoMallSeeder::SHOP_CATEGORIES), ShopCategory::query()->pluck('slug')->all());
    }

    public function test_seeds_shops_with_units_on_floors_of_the_same_mall(): void
    {
        $this->seed(DemoMallSeeder::class);

        $this->assertSame(14, Shop::query()->count());
        $this->assertSame(13, Shop::query()->listed()->count());
        $this->assertSame(2, Shop::query()->where('slug', 'food-court')->sole()->units()->count());

        ShopUnit::query()->with(['shop', 'floor'])->get()->each(
            fn (ShopUnit $unit) => $this->assertSame($unit->shop->mall_id, $unit->floor->mall_id)
        );
    }

    public function test_seeds_a_shared_catalogue_with_listings_only_active_shops_sell(): void
    {
        $this->seed(DemoMallSeeder::class);

        $this->assertSame(19, Product::query()->count());
        $water = Product::query()->where('name', 'Mineral Water')->sole();
        $this->assertSame(2, $water->variants()->count());
        $this->assertSame(5, $water->listings()->count());

        $archived = ShopProduct::query()->where('status', 'archived')->with('shop')->get();
        $this->assertNotEmpty($archived);
        $this->assertSame(['sports-zone'], $archived->pluck('shop.slug')->unique()->values()->all());
    }
}
