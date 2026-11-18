<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Enums\SummaryMetric;
use App\Modules\AnalyticsReports\Models\MonthlyReport;
use App\Modules\AnalyticsReports\Services\Reporting\MonthlyReportService;
use App\Modules\AnalyticsReports\Services\Reporting\ShopDashboardService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Monthly reports for February 2026 (28 days) with January for comparison: February has two sales worth 800
 * (300 + 500, five units), January one worth 400.
 */
class MonthlyReportTest extends ReportTestCase
{
    private function seedMonths(): void
    {
        $this->sale($this->shop, [[$this->listingA, 4]], '2026-01-15 06:00:00');
        $this->sale($this->shop, [[$this->listingA, 3]], '2026-02-10 06:00:00');
        $this->sale($this->shop, [[$this->listingB, 2]], '2026-02-20 06:00:00');
        $this->sale($this->shop, [[$this->listingA, 1]], '2026-03-02 06:00:00');
    }

    private function generate(string $month = '2026-02'): MonthlyReport
    {
        return app(MonthlyReportService::class)->generateForShop($this->owner, $this->shop, $month);
    }

    public function test_the_report_reconciles_with_the_dashboard_of_the_same_period(): void
    {
        $this->seedMonths();
        $report = $this->generate();
        $fresh = app(ShopDashboardService::class)->dashboard($this->owner, $this->shop, ReportPeriod::fromInput($this->mall, '2026-02-01', '2026-02-28'));

        $this->assertEquals($fresh['metrics'], $report->payload['dashboard']['metrics']);
        $this->assertEquals($fresh['daily_sales'], $report->payload['dashboard']['daily_sales']);
        $this->assertEquals($fresh['products'], $report->payload['dashboard']['products']);
        $this->assertEquals(800, $report->payload['dashboard']['metrics']['sales_revenue']);
        $this->assertEquals(400, $report->payload['previous']['metrics']['sales_revenue']);
        $this->assertSame('2026-01', $report->payload['previous']['month']);
        $this->assertSame(['from' => '2026-02-01', 'to' => '2026-02-28', 'timezone' => 'Asia/Dhaka'], $report->payload['period']);
        $this->assertFalse($report->partial);
        $this->assertSame(1, $report->metric_version);
    }

    public function test_the_month_boundaries_follow_the_mall_time_zone(): void
    {
        // 2026-02-28 18:00 UTC is already 1 March in Dhaka; 2026-01-31 18:00 UTC is already 1 February.
        $this->sale($this->shop, [[$this->listingA, 1]], '2026-02-28 18:00:00');
        $this->sale($this->shop, [[$this->listingA, 2]], '2026-02-28 17:59:59');
        $this->sale($this->shop, [[$this->listingA, 4]], '2026-01-31 18:00:00');
        $this->sale($this->shop, [[$this->listingA, 8]], '2026-01-31 17:59:59');

        $report = $this->generate();

        $this->assertEquals(600, $report->payload['dashboard']['metrics']['sales_revenue'], 'February holds the 4 and 2 unit sales');
        $this->assertEquals(800, $report->payload['previous']['metrics']['sales_revenue'], 'January holds the 8 unit sale');
    }

    public function test_the_narrative_is_drafted_from_the_figures_and_names_its_metrics(): void
    {
        $this->seedMonths();
        $this->stock($this->listingA, 0);
        $lines = $this->generate()->payload['narrative'];

        $this->assertStringStartsWith('Cafe Brew recorded 2 sales worth BDT 800.00 in February 2026 (average BDT 400.00 per sale, 5 units).', $lines[0]['text']);
        $this->assertStringContainsString('100.0% more than the BDT 400.00 recorded in January 2026', $lines[0]['text']);
        $this->assertSame(['sales_count', 'sales_revenue', 'average_sale_value', 'units_sold'], $lines[0]['metrics']);
        $this->assertStringContainsString('top product by revenue', implode(' ', array_column($lines, 'text')));
        $this->assertStringContainsString('1 active listings are out of stock', implode(' ', array_column($lines, 'text')));

        foreach ($lines as $line) {
            $this->assertNotSame('', $line['text']);

            foreach ($line['metrics'] as $code) {
                $this->assertTrue(ReportMetric::tryFrom($code) !== null || SummaryMetric::tryFrom($code) !== null, "{$code} is not in the metric catalogue");
            }
        }
    }

    public function test_a_month_without_sales_says_so_instead_of_inventing_a_story(): void
    {
        $lines = $this->generate('2026-01')->payload['narrative'];

        $this->assertSame('No sales were recorded for Cafe Brew in January 2026.', $lines[0]['text']);
        $this->assertCount(1, $lines);
    }

    public function test_a_month_that_is_not_over_is_marked_partial_and_stops_at_today(): void
    {
        $this->seedMonths();
        $report = $this->generate('2026-03');

        $this->assertTrue($report->partial);
        $this->assertSame('2026-03-15', $report->payload['period']['to']);
        $this->assertCount(15, $report->payload['dashboard']['daily_sales']);
        $this->assertStringContainsString('The month is not over', $report->payload['narrative'][0]['text']);
    }

    public function test_the_stored_report_is_a_snapshot_and_regenerating_replaces_it(): void
    {
        $this->seedMonths();
        $report = $this->generate();
        $this->sale($this->shop, [[$this->listingA, 5]], '2026-02-25 06:00:00');

        $this->assertSame(2, MonthlyReport::query()->find($report->getKey())->payload['dashboard']['metrics']['sales_count'], 'later records do not rewrite a stored report');

        $again = $this->generate();

        $this->assertSame($report->getKey(), $again->getKey());
        $this->assertSame(1, MonthlyReport::query()->count());
        $this->assertSame(3, $again->payload['dashboard']['metrics']['sales_count']);
    }

    public function test_a_draft_holds_what_the_report_would_store_and_stores_nothing(): void
    {
        $this->seedMonths();
        $reports = app(MonthlyReportService::class);

        $draft = $reports->draftForMall($this->admin, $this->mall, '2026-02');

        $this->assertSame(0, MonthlyReport::query()->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'report.generated']);
        $this->assertSame(2, $draft['dashboard']['metrics']['sales_count']);
        $this->assertFalse($draft['partial']);
        $this->assertNotEmpty($draft['narrative']);

        $stored = $reports->generateForMall($this->admin, $this->mall, '2026-02');

        // A stored report went through JSON, so the draft is compared in the same form.
        $this->assertSame($draft['narrative'], $stored->payload['narrative']);
        $this->assertSame(json_decode((string) json_encode($draft['dashboard']['metrics']), true), $stored->payload['dashboard']['metrics']);
    }

    public function test_only_an_administrator_of_the_mall_may_draft_its_report(): void
    {
        $this->expectException(AuthorizationException::class);

        app(MonthlyReportService::class)->draftForMall($this->owner, $this->mall, '2026-02');
    }

    public function test_future_and_malformed_months_are_refused(): void
    {
        foreach (['2026-04' => 'report.future_month', '2026-13' => 'report.invalid_month', 'February' => 'report.invalid_month', '2026-2' => 'report.invalid_month'] as $month => $rule) {
            try {
                $this->generate((string) $month);
                $this->fail("{$month} was accepted.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame($rule, $violation->rule, $month);
            }
        }

        $this->assertSame(0, MonthlyReport::query()->count());
    }

    public function test_a_mall_report_covers_the_whole_mall_and_notes_that_navigation_is_not_measured(): void
    {
        $this->seedMonths();
        $this->sale($this->otherShop, [[ShopProduct::factory()->create(['shop_id' => $this->otherShop->getKey(), 'price_amount' => '100.00']), 2]], '2026-02-12 06:00:00');

        $report = app(MonthlyReportService::class)->generateForMall($this->admin, $this->mall, '2026-02');

        $this->assertEquals(1000, $report->payload['dashboard']['metrics']['sales_revenue']);
        $this->assertSame('mall', $report->scope);
        $this->assertNull($report->shop_id);
        $this->assertStringContainsString('Navigation demand is not measured yet', implode(' ', array_column($report->payload['narrative'], 'text')));
    }

    public function test_generating_and_exporting_are_audited(): void
    {
        $this->seedMonths();
        $this->generate();

        $this->assertSame(1, DB::table('audit_logs')->where('action', 'report.generated')->count());
    }

    public function test_only_the_right_people_may_generate_or_list_reports(): void
    {
        $this->seedMonths();
        $service = app(MonthlyReportService::class);

        foreach ([
            fn () => $service->generateForShop($this->otherOwner, $this->shop, '2026-02'),
            fn () => $service->generateForShop($this->admin, $this->shop, '2026-02'),
            fn () => $service->forShop($this->otherOwner, $this->shop),
            fn () => $service->generateForMall($this->owner, $this->mall, '2026-02'),
            fn () => $service->forMall($this->owner, $this->mall),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('An unauthorised user reached a report.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, MonthlyReport::query()->count());
    }

    public function test_the_selectable_months_are_the_current_month_and_eleven_before_it(): void
    {
        $months = app(MonthlyReportService::class)->selectableMonths($this->mall);

        $this->assertCount(12, $months);
        $this->assertSame('2026-03', $months[0]);
        $this->assertSame('2025-04', $months[11]);
    }
}
