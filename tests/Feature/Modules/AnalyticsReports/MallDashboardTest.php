<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AnalyticsReports\Services\Reporting\MallDashboardService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The mall dashboard against hand-calculated fixtures for 1 to 10 March 2026 in Dhaka. Revenue figures: Cafe Brew
 * 550 (the fixture of ShopDashboardTest), Book Nook 300, and four more shops with 5000, 3000, 1200 and 800.
 */
class MallDashboardTest extends ReportTestCase
{
    /** @var array<string, Shop> */
    private array $shops = [];

    private function dashboard(): array
    {
        return app(MallDashboardService::class)->dashboard($this->admin, $this->mall, ReportPeriod::fromInput($this->mall, '2026-03-01', '2026-03-10'));
    }

    private function shopWithSales(string $name, int $units): Shop
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => $name, 'status' => 'active']);
        $listing = ShopProduct::factory()->create(['shop_id' => $shop->getKey(), 'price_amount' => '100.00']);
        $this->sale($shop, [[$listing, $units]], '2026-03-04 06:00:00');

        return $this->shops[$name] = $shop;
    }

    private function seedMall(): void
    {
        $this->sale($this->shop, [[$this->listingA, 2]], '2026-02-28 18:00:00');
        $this->sale($this->shop, [[$this->listingA, 1]], '2026-03-05 06:00:00');
        $this->sale($this->shop, [[$this->listingB, 1]], '2026-03-10 17:59:59');
        $this->sale($this->shop, [[$this->listingA, 20]], '2026-02-25 06:00:00'); // the 10 days before the period: 2000
        $this->sale($this->otherShop, [[ShopProduct::factory()->create(['shop_id' => $this->otherShop->getKey(), 'price_amount' => '100.00']), 3]], '2026-03-04 06:00:00');

        foreach (['Gadget Hub' => 50, 'Style Avenue' => 30, 'Phone Fix' => 12, 'Fresh Mart' => 8] as $name => $units) {
            $this->shopWithSales($name, $units);
        }

        $this->complaint('CMP-1', 'cleanliness', null, 'resolved', '2026-03-02 05:00:00', '2026-03-02 17:00:00');
        $this->complaint('CMP-2', 'shop_service', $this->otherShop, 'open', '2026-03-03 05:00:00');
        $this->complaint('CMP-3', 'shop_service', $this->otherShop, 'assigned', '2026-03-04 05:00:00');
        $this->complaint('CMP-4', 'shop_service', $this->otherShop, 'in_progress', '2026-03-05 05:00:00');
        $this->complaint('CMP-5', 'cleanliness', null, 'closed', '2026-03-06 05:00:00', '2026-03-07 05:00:00');

        $this->review($this->shop, 5, '2026-03-02 05:00:00');
        $this->review($this->shop, 3, '2026-03-04 05:00:00');
        $this->review($this->otherShop, 4, '2026-03-04 05:00:00');
    }

    public function test_the_mall_totals_match_the_hand_calculation(): void
    {
        $this->seedMall();
        $m = $this->dashboard()['metrics'];

        // 550 + 300 + 5000 + 3000 + 1200 + 800
        $this->assertSame(10850.0, $m['sales_revenue']);
        $this->assertSame(8, $m['sales_count']);
        $this->assertSame(1356.25, $m['average_sale_value']);
        $this->assertSame(6, $m['shops_with_sales']);
        $this->assertSame(6, $m['active_shops']);
        $this->assertSame(5, $m['complaints_filed']);
        $this->assertSame(2, $m['complaints_resolved']);
        $this->assertSame(3, $m['complaints_unresolved']);
        $this->assertSame(18.0, $m['complaint_resolution_hours']);
        $this->assertSame(3, $m['reviews_published']);
        $this->assertSame(4.0, $m['rating_average']);
        $this->assertNull($m['navigation_requests']);
    }

    public function test_the_shop_comparison_shows_counts_shares_and_changes(): void
    {
        $this->seedMall();
        $report = $this->dashboard();
        $tenants = array_column($report['tenants'], null, 'name');

        $this->assertSame('Gadget Hub', $report['tenants'][0]['name'], 'ranked by revenue');
        $this->assertSame(5000.0, $tenants['Gadget Hub']['revenue']);
        $this->assertSame(46.1, $tenants['Gadget Hub']['revenue_share']);
        $this->assertSame(550.0, $tenants['Cafe Brew']['revenue']);
        $this->assertSame(2000.0, $tenants['Cafe Brew']['previous_revenue']);
        $this->assertSame(-72.5, $tenants['Cafe Brew']['change_percent']);
        $this->assertNull($tenants['Gadget Hub']['change_percent'], 'no sales before the period, so no percentage');
        $this->assertSame(2, $tenants['Cafe Brew']['reviews']);
        $this->assertSame(4.0, $tenants['Cafe Brew']['rating']);
        $this->assertSame(3, $tenants['Book Nook']['complaints']);
        $this->assertEqualsWithDelta(10850.0, array_sum(array_column($report['tenants'], 'revenue')), 0.001);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($report['tenants'], 'revenue_share')), 0.2);
        $this->assertSame(6, $report['coverage']['shops_with_sales']);
        $this->assertSame(2, $report['coverage']['shops_with_reviews']);
        $this->assertSame(0, $report['coverage']['shops_with_five_reviews']);
    }

    public function test_the_adjusted_rating_pulls_small_samples_towards_the_mall_mean(): void
    {
        $this->seedMall();
        // Gadget Hub gets a single 5-star review; Cafe Brew has 2 reviews (5 and 3), Book Nook one 4-star review.
        $this->review($this->shops['Gadget Hub'], 5, '2026-03-05 05:00:00');
        $tenants = array_column($this->dashboard()['tenants'], null, 'name');

        $this->assertSame(5.0, $tenants['Gadget Hub']['rating']);
        $this->assertLessThan(5.0, $tenants['Gadget Hub']['rating_adjusted']);
        $this->assertGreaterThan(4.0, $tenants['Gadget Hub']['rating_adjusted']);
        $this->assertNull($tenants['Phone Fix']['rating_adjusted'], 'no reviews, no rating to adjust');
    }

    public function test_complaints_are_summarised_by_category_and_status(): void
    {
        $this->seedMall();
        $c = $this->dashboard()['complaints'];

        $this->assertSame(['shop_service' => 3, 'cleanliness' => 2], $c['by_category']);
        $this->assertSame(['assigned' => 1, 'closed' => 1, 'in_progress' => 1, 'open' => 1, 'resolved' => 1], $c['by_status']);
        $this->assertSame(18.0, $c['median_hours']);
    }

    public function test_quality_flags_cite_their_evidence(): void
    {
        $this->seedMall();

        // Ten reviews for Gadget Hub whose ratings slide from 5 to 1 over 45 days.
        foreach ([5, 5, 5, 4, 4, 3, 3, 2, 2, 1] as $i => $rating) {
            $this->review($this->shops['Gadget Hub'], $rating, CarbonImmutable::parse('2026-01-20 05:00:00', 'UTC')->addDays($i * 5)->format('Y-m-d H:i:s'));
        }

        $flags = collect($this->dashboard()['quality_flags']);
        $kinds = fn (string $shop) => $flags->where('shop', $shop)->pluck('kind')->sort()->values()->all();

        $this->assertSame(['rating_decline'], $kinds('Gadget Hub'));
        $this->assertSame(['complaint_outlier', 'unresolved_complaints'], $kinds('Book Nook'));
        $this->assertSame(['revenue_drop'], $kinds('Cafe Brew'));
        $this->assertSame([], $kinds('Style Avenue'));

        $this->assertStringContainsString('CMP-2, CMP-3, CMP-4', $flags->firstWhere('kind', 'unresolved_complaints')['evidence']);
        $this->assertStringContainsString('fell 72.5%', $flags->firstWhere('kind', 'revenue_drop')['evidence']);
        $this->assertStringContainsString('10 reviews in the last 120 days', $flags->firstWhere('kind', 'rating_decline')['evidence']);
    }

    public function test_segments_group_the_shops_that_have_activity(): void
    {
        $this->seedMall();
        $report = $this->dashboard();
        $segments = $report['segments'];

        $this->assertTrue($segments['available']);
        $this->assertGreaterThanOrEqual(2, $segments['k']);
        $this->assertSame(6, array_sum(array_map(fn (array $g): int => count($g['shops']), $segments['groups'])));
        $this->assertContains('Gadget Hub', $segments['groups'][0]['shops'], 'the first segment has the highest mean revenue');
        $this->assertGreaterThan($segments['groups'][1]['mean_revenue'], $segments['groups'][0]['mean_revenue']);

        foreach ($report['tenants'] as $tenant) {
            $this->assertNotNull($tenant['segment']);
        }
    }

    public function test_segments_say_why_they_are_missing_when_there_are_too_few_shops(): void
    {
        $this->sale($this->shop, [[$this->listingA, 2]], '2026-03-04 06:00:00');
        $segments = $this->dashboard()['segments'];

        $this->assertFalse($segments['available']);
        $this->assertStringContainsString('at least 5 shops', $segments['reason']);
    }

    public function test_floor_and_category_demand_use_the_shops_unit_and_category(): void
    {
        $this->seedMall();
        $ground = Floor::factory()->create(['mall_id' => $this->mall->getKey(), 'code' => 'G', 'name' => 'Ground Floor']);
        ShopUnit::factory()->create(['shop_id' => $this->shops['Gadget Hub']->getKey(), 'floor_id' => $ground->getKey()]);
        ShopUnit::factory()->create(['shop_id' => $this->shop->getKey(), 'floor_id' => $ground->getKey()]);

        $report = $this->dashboard();
        $floors = array_column($report['floors'], null, 'name');

        $this->assertSame(5550.0, $floors['Ground Floor']['revenue']);
        $this->assertSame(2, $floors['Ground Floor']['shops']);
        $this->assertSame(5300.0, $floors['No floor assigned']['revenue']);
        $this->assertEqualsWithDelta(10850.0, array_sum(array_column($report['floors'], 'revenue')), 0.001);
        $this->assertEqualsWithDelta(10850.0, array_sum(array_column($report['categories'], 'revenue')), 0.001);
    }

    public function test_navigation_demand_is_reported_as_not_available_and_never_as_footfall(): void
    {
        $navigation = $this->dashboard()['navigation'];

        $this->assertFalse($navigation['available']);
        $this->assertStringContainsString('not built', $navigation['reason']);
    }

    public function test_another_malls_records_are_never_counted(): void
    {
        $this->seedMall();
        $other = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);
        $foreign = Shop::factory()->create(['mall_id' => $other->getKey(), 'status' => 'active']);
        $this->sale($foreign, [[ShopProduct::factory()->create(['shop_id' => $foreign->getKey(), 'price_amount' => '100.00']), 99]], '2026-03-04 06:00:00');

        $report = $this->dashboard();

        $this->assertSame(10850.0, $report['metrics']['sales_revenue']);
        $this->assertNotContains($foreign->name, array_column($report['tenants'], 'name'));
    }

    public function test_only_administrators_of_the_mall_may_open_it(): void
    {
        $period = ReportPeriod::fromInput($this->mall, '2026-03-01', '2026-03-10');

        foreach ([$this->owner, $this->customer] as $user) {
            try {
                app(MallDashboardService::class)->dashboard($user, $this->mall, $period);
                $this->fail('A user who does not administer the mall read its dashboard.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
