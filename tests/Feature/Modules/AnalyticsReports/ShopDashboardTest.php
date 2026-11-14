<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AnalyticsReports\Services\Reporting\CsvExporter;
use App\Modules\AnalyticsReports\Services\Reporting\ShopDashboardService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Reconciliation of the shop dashboard against hand-calculated fixtures: the period is 1 to 10 March 2026 in
 * Dhaka (UTC+6), so it runs from 2026-02-28 18:00 UTC up to but not including 2026-03-10 18:00 UTC.
 */
class ShopDashboardTest extends ReportTestCase
{
    private function dashboard(?string $from = '2026-03-01', ?string $to = '2026-03-10'): array
    {
        return app(ShopDashboardService::class)->dashboard($this->owner, $this->shop, ReportPeriod::fromInput($this->mall, $from, $to));
    }

    private function seedMonth(): void
    {
        // Inside the period, including both boundary seconds.
        $this->sale($this->shop, [[$this->listingA, 2]], '2026-02-28 18:00:00');        // 200.00, 1 March 00:00 in Dhaka
        $this->sale($this->shop, [[$this->listingA, 1]], '2026-03-05 06:00:00');        // 100.00
        $this->sale($this->shop, [[$this->listingB, 1]], '2026-03-10 17:59:59');        // 250.00, 10 March 23:59:59 in Dhaka
        // Outside: a second before, the second the period ends, a voided sale, another shop's sale.
        $this->sale($this->shop, [[$this->listingA, 1]], '2026-02-28 17:59:59');
        $this->sale($this->shop, [[$this->listingA, 1]], '2026-03-10 18:00:00');
        $this->sale($this->shop, [[$this->listingB, 4]], '2026-03-04 09:00:00', voided: true);
        $this->sale($this->otherShop, [[ShopProduct::factory()->create(['shop_id' => $this->otherShop->getKey(), 'price_amount' => '100.00']), 5]], '2026-03-04 09:00:00');

        $this->order($this->shop, 'requested', 'reservation', '2026-03-02 05:00:00');
        $this->order($this->shop, 'completed', 'click_and_collect', '2026-03-03 05:00:00');
        $this->order($this->shop, 'completed', 'reservation', '2026-03-04 05:00:00');
        $this->order($this->shop, 'cancelled', 'click_and_collect', '2026-03-05 05:00:00');
        $this->order($this->shop, 'completed', 'reservation', '2026-03-12 05:00:00');
        $this->order($this->otherShop, 'completed', 'reservation', '2026-03-03 05:00:00');

        $this->stock($this->listingA, 0);
        $this->stock($this->listingB, 3, 5);

        $this->review($this->shop, 5, '2026-03-02 05:00:00');
        $this->review($this->shop, 4, '2026-03-03 05:00:00');
        $this->review($this->shop, 3, '2026-03-04 05:00:00');
        $this->review($this->shop, 1, '2026-03-05 05:00:00', status: 'hidden');
        $this->review($this->shop, 1, '2026-02-20 05:00:00');
        $this->review($this->otherShop, 2, '2026-03-04 05:00:00');
    }

    public function test_the_figures_match_the_hand_calculation(): void
    {
        $this->seedMonth();
        $report = $this->dashboard();
        $m = $report['metrics'];

        $this->assertSame(550.0, $m['sales_revenue']);
        $this->assertSame(3, $m['sales_count']);
        $this->assertSame(183.33, $m['average_sale_value']);
        $this->assertSame(4, $m['units_sold']);
        $this->assertSame(4, $m['orders_placed']);
        $this->assertSame(2, $m['orders_completed']);
        $this->assertSame(2, $m['reservations_placed']);
        $this->assertSame(50.0, $m['order_completion_rate']);
        $this->assertSame(1, $m['stock_out_listings']);
        $this->assertSame(1, $m['low_stock_listings']);
        $this->assertSame(3, $m['stock_units_on_hand']);
        $this->assertSame(3, $m['reviews_published']);
        $this->assertSame(4.0, $m['rating_average']);
        $this->assertSame(['cancelled' => 1, 'completed' => 2, 'requested' => 1], $report['orders']['by_status']);
    }

    public function test_the_days_follow_the_mall_time_zone_and_add_up_to_the_revenue(): void
    {
        $this->seedMonth();
        $report = $this->dashboard();

        $this->assertCount(10, $report['daily_sales']);
        $this->assertSame(200.0, $report['daily_sales']['2026-03-01']);
        $this->assertSame(100.0, $report['daily_sales']['2026-03-05']);
        $this->assertSame(250.0, $report['daily_sales']['2026-03-10']);
        $this->assertSame(0.0, $report['daily_sales']['2026-03-02']);
        $this->assertEqualsWithDelta($report['metrics']['sales_revenue'], array_sum($report['daily_sales']), 0.001);
        $this->assertEqualsWithDelta($report['metrics']['sales_revenue'], array_sum(array_column($report['products'], 'revenue')), 0.001);
        $this->assertSame($report['metrics']['units_sold'], array_sum(array_column($report['products'], 'units')));
    }

    public function test_the_product_table_ranks_listings_and_shows_stock(): void
    {
        $this->seedMonth();
        $products = $this->dashboard()['products'];

        $this->assertCount(2, $products);
        $this->assertSame(300.0, $products[0]['revenue']);
        $this->assertSame(3, $products[0]['units']);
        $this->assertSame('A', $products[0]['abc']);
        $this->assertSame($this->listingA->getKey(), $products[0]['listing']);
        $this->assertSame(250.0, $products[1]['revenue']);
        $this->assertSame(45.5, $products[1]['share']);
        $this->assertSame('A', $products[1]['abc'], 'the second listing starts below 80% of revenue, so it is still A');
        $this->assertSame(0, $products[0]['on_hand']);
        $this->assertNull($products[0]['xyz'], 'ten days hold one full week only, too few for a demand pattern');
        $this->assertFalse($products[0]['slow_moving']);
    }

    public function test_the_other_shops_records_never_appear(): void
    {
        $this->seedMonth();
        $report = $this->dashboard();

        $this->assertStringNotContainsString('Book Nook', json_encode($report));
        $this->assertSame(0, $report['metrics']['coupon_redemptions']);
        $this->assertSame([], $report['campaigns']);
    }

    public function test_an_empty_period_shows_zeros_and_empty_values_not_errors(): void
    {
        $report = $this->dashboard('2026-01-01', '2026-01-10');
        $m = $report['metrics'];

        $this->assertSame(0.0, $m['sales_revenue']);
        $this->assertSame(0, $m['sales_count']);
        $this->assertNull($m['average_sale_value']);
        $this->assertNull($m['order_completion_rate']);
        $this->assertNull($m['rating_average']);
        $this->assertSame(0, $report['feedback']['count']);
        $this->assertFalse($report['anomalies']['available']);
    }

    public function test_unusual_sales_days_are_found_against_the_weekday_norm(): void
    {
        // Eight weeks. Ordinary days sell 1 or 2 units (100 or 200), ordinary Saturdays 3 or 4; one Wednesday sells 50 and one Saturday nothing.
        $day = CarbonImmutable::parse('2026-01-05 06:00:00', 'UTC');

        for ($i = 0; $i < 56; $i++) {
            $date = $day->addDays($i);
            $amount = ($date->dayOfWeekIso === 6 ? 3 : 1) + ($i % 2);
            $amount = $i === 30 ? 50 : $amount;

            if ($i !== 40) {
                $this->sale($this->shop, [[$this->listingA, $amount]], $date->format('Y-m-d H:i:s'));
            }
        }

        $report = app(ShopDashboardService::class)->dashboard($this->owner, $this->shop, ReportPeriod::fromInput($this->mall, '2026-01-05', '2026-03-01'));
        $days = array_column($report['anomalies']['days'], 'direction', 'day');

        $this->assertTrue($report['anomalies']['available']);
        $this->assertSame('high', $days[$day->addDays(30)->format('Y-m-d')]);
        $this->assertSame('low', $days[$day->addDays(40)->format('Y-m-d')]);
        $this->assertCount(2, $days, 'ordinary busy Saturdays are not unusual');
    }

    public function test_only_the_shops_owner_may_open_its_dashboard(): void
    {
        $period = ReportPeriod::fromInput($this->mall, '2026-03-01', '2026-03-10');

        foreach ([$this->otherOwner, $this->admin, $this->customer] as $user) {
            try {
                app(ShopDashboardService::class)->dashboard($user, $this->shop, $period);
                $this->fail('A user who does not own the shop read its dashboard.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_csv_export_matches_the_dashboard_and_marks_simulated_records(): void
    {
        $this->sale($this->shop, [[$this->listingA, 2]], '2026-03-02 05:00:00', receipt: 'SIM-1-001-001');
        $this->sale($this->shop, [[$this->listingB, 1]], '2026-03-03 05:00:00');
        $report = $this->dashboard();

        $csv = app(CsvExporter::class)->export(['scope' => 'shop', 'subject' => 'Cafe Brew', 'label' => 'Shop dashboard', 'period_from' => '2026-03-01', 'period_to' => '2026-03-10', 'timezone' => 'Asia/Dhaka', 'generated' => 'now'], $report);

        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertStringContainsString('sales_revenue,"Recorded sales revenue",450.00,BDT', $csv);
        $this->assertStringContainsString('sales_count,"Recorded sales",2,sales', $csv);
        $this->assertStringContainsString('2026-03-02,200.00', $csv);
        $this->assertStringContainsString('Simulated demonstration records included: 1 of 2 sales', $csv);
        $this->assertStringNotContainsString('Book Nook', $csv);
    }

    public function test_a_shop_or_product_name_cannot_run_as_a_spreadsheet_formula(): void
    {
        $this->shop->forceFill(['name' => '=HYPERLINK("http://example.test")'])->save();
        $report = $this->dashboard();

        $csv = app(CsvExporter::class)->export(['scope' => 'shop', 'subject' => $this->shop->name, 'label' => 'Shop dashboard', 'period_from' => '2026-03-01', 'period_to' => '2026-03-10', 'timezone' => 'Asia/Dhaka', 'generated' => 'now'], $report);

        $this->assertStringContainsString("Subject,\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Subject,"=HYPERLINK', $csv);
    }
}
