<?php

namespace Tests\Feature\Seeders;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
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
        $this->assertSame(['G', 'F1'], $mall->floors()->pluck('code')->all());
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
}
