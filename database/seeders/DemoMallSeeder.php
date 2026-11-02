<?php

namespace Database\Seeders;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Seeder;

/**
 * Synthetic demonstration data for one fictional mall. Names, brands and prices
 * are invented; never use this seeder for tests (use factories).
 */
class DemoMallSeeder extends Seeder
{
    public const MALL_SLUG = 'demo-city-mall';

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
    }

    private function seedFloors(Mall $mall): void
    {
        $mall->floors()->createMany([
            ['code' => 'G', 'name' => 'Ground Floor', 'sort_order' => 0, 'elevation_m' => 0, 'status' => 'active'],
            ['code' => 'F1', 'name' => 'First Floor', 'sort_order' => 1, 'elevation_m' => 4.5, 'status' => 'active'],
        ]);
    }
}
