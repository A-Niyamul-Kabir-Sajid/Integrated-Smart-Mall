<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Services\Reporting\ForecastService;
use App\Modules\AnalyticsReports\Services\Reporting\MallDashboardService;
use App\Modules\AnalyticsReports\Services\Reporting\ShopDashboardService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoMallSeeder;
use Database\Seeders\DemoSalesHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The simulated demonstration history: marked as simulated, repeatable, never touching real records, and
 * telling the four stories the dashboards are meant to show.
 */
class DemoSalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_history_is_marked_repeatable_and_tells_its_stories(): void
    {
        $this->seed([DemoMallSeeder::class, DemoAccountsSeeder::class]);

        $mall = Mall::query()->where('slug', DemoMallSeeder::MALL_SLUG)->firstOrFail();
        $cafe = Shop::query()->where('name', 'Cafe Brew')->firstOrFail();

        // A real complaint made before seeding must survive it.
        $realShop = Shop::query()->where('name', 'Juice Corner')->firstOrFail();
        DB::table('complaints')->insert([
            'complaint_id' => '11111111-1111-4111-8111-111111111111', 'complaint_number' => 'CMP-REAL0001', 'mall_id' => $mall->getKey(), 'customer_user_id' => User::query()->where('email', 'customer@demo.test')->value('user_id'),
            'shop_id' => $realShop->getKey(), 'category' => 'noise', 'subject' => 'Real', 'description' => 'Real', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->seed(DemoSalesHistorySeeder::class);
        $counts = fn (): array => [DB::table('purchases')->count(), DB::table('shop_orders')->count(), DB::table('reviews')->count(), DB::table('complaints')->count(), DB::table('users')->count()];
        $first = $counts();

        $this->seed(DemoSalesHistorySeeder::class);

        $this->assertSame($first, $counts(), 'running the seeder again replaces the simulated records instead of adding to them');
        $this->assertSame(1, DB::table('complaints')->where('complaint_number', 'CMP-REAL0001')->count(), 'real records are untouched');
        $this->assertSame(0, DB::table('purchases')->where('external_receipt_ref', 'not like', 'SIM-%')->count());
        $this->assertGreaterThan(500, DB::table('purchases')->where('external_receipt_ref', 'like', 'SIM-%')->count());

        $period = ReportPeriod::fromInput($mall, null, null);
        $admin = User::query()->where('email', 'mall.admin@demo.test')->firstOrFail();
        $owner = User::query()->where('email', 'owner@demo.test')->firstOrFail();

        $dashboard = app(MallDashboardService::class)->dashboard($admin, $mall, $period);
        $flags = collect($dashboard['quality_flags']);

        $this->assertTrue($dashboard['provenance']['any'], 'the dashboard says part of its data is simulated');
        $this->assertSame($dashboard['provenance']['sales']['total'], $dashboard['provenance']['sales']['simulated']);
        $this->assertTrue($flags->contains(fn (array $f): bool => $f['shop'] === 'Cafe Brew' && $f['kind'] === 'rating_decline'));
        $this->assertTrue($flags->contains(fn (array $f): bool => $f['shop'] === 'Phone Fix' && $f['kind'] === 'revenue_drop'));
        $this->assertTrue($flags->contains(fn (array $f): bool => $f['shop'] === 'Book Nook' && $f['kind'] === 'unresolved_complaints'));
        $this->assertTrue($dashboard['segments']['available']);

        $shop = app(ShopDashboardService::class)->dashboard($owner, $cafe, $period);
        $this->assertGreaterThan(0, $shop['metrics']['sales_revenue']);
        $this->assertTrue($shop['provenance']['any']);

        $forecast = app(ForecastService::class)->forShop($owner, $cafe);
        $this->assertSame('ok', $forecast['status']);
        $this->assertGreaterThan(0, $forecast['provenance']['simulated']);
        $this->assertCount(14, $forecast['forecast']);
    }
}
