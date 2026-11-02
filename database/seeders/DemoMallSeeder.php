<?php

namespace Database\Seeders;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use Illuminate\Database\Seeder;

/**
 * Synthetic demonstration data for one fictional mall. Names, brands and prices
 * are invented; never use this seeder for tests (use factories).
 */
class DemoMallSeeder extends Seeder
{
    public const MALL_SLUG = 'demo-city-mall';

    /**
     * Shop category slug => name.
     */
    public const SHOP_CATEGORIES = [
        'food-drinks' => 'Food & Drinks',
        'fashion' => 'Fashion',
        'electronics' => 'Electronics',
        'groceries' => 'Groceries',
        'health-beauty' => 'Health & Beauty',
        'books-stationery' => 'Books & Stationery',
        'services' => 'Services',
    ];

    public function run(): void
    {
        $mall = Mall::query()->create([
            'slug' => self::MALL_SLUG,
            'name' => 'Demo City Mall',
            'address' => 'Demo Road 32, Sonadanga',
            'city' => 'Khulna',
            'timezone' => 'Asia/Dhaka',
            'status' => MallStatus::Active,
        ]);

        $this->seedFloors($mall);
        $this->seedShopCategories();
    }

    private function seedFloors(Mall $mall): void
    {
        $mall->floors()->createMany([
            ['code' => 'G', 'name' => 'Ground Floor', 'sort_order' => 0, 'elevation_m' => 0, 'status' => 'active'],
            ['code' => 'F1', 'name' => 'First Floor', 'sort_order' => 1, 'elevation_m' => 4.5, 'status' => 'active'],
        ]);
    }

    private function seedShopCategories(): void
    {
        foreach (self::SHOP_CATEGORIES as $slug => $name) {
            ShopCategory::query()->create(['slug' => $slug, 'name' => $name]);
        }
    }
}
