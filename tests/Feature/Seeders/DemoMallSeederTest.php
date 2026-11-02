<?php

namespace Tests\Feature\Seeders;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\ShopCategory;
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
}
