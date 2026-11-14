<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AnalyticsReports\Services\Reporting\CsvExporter;
use App\Modules\AnalyticsReports\Services\Reporting\MallDashboardService;
use App\Modules\AnalyticsReports\Services\Reporting\MonthlyReportService;
use App\Modules\AnalyticsReports\Services\Reporting\ShopDashboardService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use Illuminate\Support\Facades\DB;

/**
 * EPIC-14 figures inside the EPIC-12 dashboards, reports and exports (ISM-183): real traffic only, the
 * shop's own rows only, and the same numbers on screen, in the report and in the file.
 */
class ActivityIntegrationTest extends ReportTestCase
{
    private function summary(string $metric, string $subject, int $value, int $secondary, string $label = 'real', string $kind = '', string $day = '2026-02-10'): void
    {
        DB::table('activity_daily_summaries')->insert([
            'mall_id' => $this->mall->getKey(), 'day' => $day, 'metric_code' => $metric, 'kind' => $kind, 'subject_key' => $subject, 'shop_id' => null,
            'value' => $value, 'secondary' => $secondary, 'dataset_label' => $label, 'formula_version' => 1, 'source_cutoff_at' => now(),
        ]);
    }

    private function seedActivity(): void
    {
        $this->summary('shop_page_views', $this->shop->getKey(), 12, 9);
        $this->summary('shop_page_views', $this->shop->getKey(), 500, 400, 'demo');
        $this->summary('shop_page_views', $this->otherShop->getKey(), 77, 70);
        $this->summary('search_total', '', 30, 8, kind: 'shop');
        $this->summary('search_term_count', 'umbrella', 8, 0, kind: 'shop');
        $this->summary('search_term_zero_result_count', 'umbrella', 8, 0, kind: 'shop');
        $this->summary('search_total', '', 999, 999, 'demo', 'shop');
    }

    public function test_a_shop_sees_only_its_own_real_views(): void
    {
        $this->seedActivity();
        $report = app(ShopDashboardService::class)->dashboard($this->owner, $this->shop, ReportPeriod::fromInput($this->mall, '2026-02-01', '2026-02-28'));

        $this->assertSame(12, $report['visitors']['shop_views']);
        $this->assertSame(9, $report['visitors']['shop_visitors']);
        $this->assertStringNotContainsString('77', json_encode($report['visitors']));
    }

    public function test_the_mall_sees_real_searches_and_views_but_never_demonstration_traffic(): void
    {
        $this->seedActivity();
        $report = app(MallDashboardService::class)->dashboard($this->admin, $this->mall, ReportPeriod::fromInput($this->mall, '2026-02-01', '2026-02-28'));

        $this->assertSame(30, $report['search']['searches']);
        $this->assertSame(8, $report['search']['zero_results']);
        $this->assertSame('umbrella', $report['search']['unmet'][0]['term']);
        $this->assertSame(89, $report['page_views']['shop_views'], '12 + 77, not the 500 demonstration views');
    }

    public function test_the_report_and_the_files_carry_the_same_numbers(): void
    {
        $this->seedActivity();
        $this->sale($this->shop, [[$this->listingA, 1]], '2026-02-10 06:00:00');

        $shopReport = app(MonthlyReportService::class)->generateForShop($this->owner, $this->shop, '2026-02');
        $mallReport = app(MonthlyReportService::class)->generateForMall($this->admin, $this->mall, '2026-02');

        $this->assertSame(12, $shopReport->payload['dashboard']['visitors']['shop_views']);
        $this->assertStringContainsString('opened 12 times by 9 visitor-days', implode(' ', array_column($shopReport->payload['narrative'], 'text')));
        $this->assertStringContainsString('"umbrella" (8 times)', implode(' ', array_column($mallReport->payload['narrative'], 'text')));

        $meta = ['scope' => 'shop', 'subject' => 'Cafe Brew', 'label' => 'Test', 'period_from' => '2026-02-01', 'period_to' => '2026-02-28', 'timezone' => 'Asia/Dhaka', 'generated' => 'now'];
        $shopCsv = app(CsvExporter::class)->export($meta, $shopReport->payload['dashboard']);
        $mallCsv = app(CsvExporter::class)->export(['scope' => 'mall'] + $meta, $mallReport->payload['dashboard']);

        $this->assertStringContainsString("shop_page_views,12\n", $shopCsv);
        $this->assertStringContainsString('visitor-days,9', $shopCsv);
        $this->assertStringNotContainsString('77', $shopCsv);
        $this->assertStringContainsString("search_total,30\n", $mallCsv);
        $this->assertStringContainsString("umbrella,8\n", $mallCsv);
        $this->assertStringContainsString("shop_page_views,89\n", $mallCsv);
        $this->assertStringNotContainsString('999', $mallCsv);
    }
}
