<?php

namespace Database\Seeders;

use App\Modules\AnalyticsReports\Services\DemoTrafficService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Seeder;

/**
 * Demonstration search and page-view traffic for the demo mall, labelled "demo" in every row so it is
 * never mixed with real visitors. Run again to replace it; real rows are untouched.
 */
class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $mall = Mall::query()->where('slug', DemoMallSeeder::MALL_SLUG)->first();

        if ($mall !== null) {
            app(DemoTrafficService::class)->seed($mall);
        }
    }
}
