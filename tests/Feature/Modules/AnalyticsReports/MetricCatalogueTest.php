<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Enums\SummaryMetric;
use App\Modules\AnalyticsReports\Services\Reporting\MallDashboardService;
use App\Modules\AnalyticsReports\Services\Reporting\MetricCatalogue;
use App\Modules\AnalyticsReports\Services\Reporting\ShopDashboardService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;

/**
 * The metric catalogue is a contract: every figure a dashboard, report or export shows is defined in it
 * (source, formula, dimensions, refresh, access), and every definition is complete.
 */
class MetricCatalogueTest extends ReportTestCase
{
    public function test_every_metric_is_completely_defined(): void
    {
        foreach (ReportMetric::cases() as $metric) {
            $this->assertNotSame('', $metric->label(), $metric->value);
            $this->assertNotSame('', $metric->formula(), $metric->value);
            $this->assertNotSame('', $metric->dimensions(), $metric->value);
            $this->assertNotSame('', $metric->refresh(), $metric->value);
            $this->assertNotSame('', $metric->visibility(), $metric->value);
            $this->assertNotSame('', $metric->unit(), $metric->value);
            $this->assertContains($metric->scope(), ['shop', 'mall', 'both'], $metric->value);
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $metric->value);

            if ($metric->available()) {
                $this->assertNotEmpty($metric->sources(), "{$metric->value} names no source");
            }
        }
    }

    public function test_only_navigation_demand_is_defined_but_unavailable(): void
    {
        $unavailable = array_filter(ReportMetric::cases(), fn (ReportMetric $m): bool => ! $m->available());

        $this->assertSame([ReportMetric::NavigationRequests], array_values($unavailable));
        $this->assertStringContainsString('EPIC-05', ReportMetric::NavigationRequests->unavailableReason());
        $this->assertStringContainsString('never confirmed visits', ReportMetric::NavigationRequests->formula());
    }

    public function test_metric_codes_are_unique_across_both_catalogues(): void
    {
        $codes = [...array_map(fn (ReportMetric $m): string => $m->value, ReportMetric::cases()), ...array_map(fn (SummaryMetric $m): string => $m->value, SummaryMetric::cases())];

        $this->assertSame($codes, array_values(array_unique($codes)));
    }

    public function test_shop_and_mall_catalogues_follow_the_access_matrix(): void
    {
        $shop = array_column(app(MetricCatalogue::class)->rows(ReportMetric::SHOP), null, 'code');
        $mall = array_column(app(MetricCatalogue::class)->rows(ReportMetric::MALL), null, 'code');

        foreach (ReportMetric::cases() as $metric) {
            $this->assertSame($metric->visibleToShop(), isset($shop[$metric->value]), "{$metric->value} for shops");
            $this->assertSame($metric->visibleToMall(), isset($mall[$metric->value]), "{$metric->value} for malls");
        }

        $this->assertArrayHasKey('shop_page_views', $shop);
        $this->assertArrayNotHasKey('search_total', $shop, 'search demand belongs to the mall only');
        $this->assertArrayHasKey('search_total', $mall);
        $this->assertArrayNotHasKey('stock_units_on_hand', $mall);
        $this->assertArrayNotHasKey('complaints_filed', $shop);
    }

    public function test_every_figure_a_dashboard_returns_is_in_the_catalogue_for_its_reader(): void
    {
        $period = ReportPeriod::fromInput($this->mall, '2026-03-01', '2026-03-10');
        $shopCodes = array_keys(app(ShopDashboardService::class)->dashboard($this->owner, $this->shop, $period)['metrics']);
        $mallCodes = array_keys(app(MallDashboardService::class)->dashboard($this->admin, $this->mall, $period)['metrics']);

        $shopCatalogue = array_column(app(MetricCatalogue::class)->rows(ReportMetric::SHOP), 'code');
        $mallCatalogue = array_column(app(MetricCatalogue::class)->rows(ReportMetric::MALL), 'code');

        $this->assertNotEmpty($shopCodes);
        $this->assertNotEmpty($mallCodes);
        $this->assertSame([], array_values(array_diff($shopCodes, $shopCatalogue)), 'a shop dashboard figure is missing from the shop catalogue');
        $this->assertSame([], array_values(array_diff($mallCodes, $mallCatalogue)), 'a mall dashboard figure is missing from the mall catalogue');
    }

    public function test_analysis_methods_are_named_in_the_catalogue(): void
    {
        $this->assertStringContainsString('Ridge regression', ReportMetric::SalesForecast->algorithm());
        $this->assertStringContainsString('Holt', ReportMetric::SalesForecast->algorithm());
        $this->assertStringContainsString('k-means', ReportMetric::ShopSegments->algorithm());
        $this->assertStringContainsString('Empirical-Bayes', ReportMetric::RatingAdjusted->algorithm());
        $this->assertStringContainsString('modified z-score', ReportMetric::SalesAnomalies->algorithm());
        $this->assertStringContainsString('least squares', ReportMetric::RatingTrend->algorithm());
        $this->assertStringContainsString('Pareto', ReportMetric::ProductClasses->algorithm());
        $this->assertStringContainsString('log-odds', ReportMetric::ReviewTerms->algorithm());
        $this->assertNull(ReportMetric::SalesRevenue->algorithm());
    }
}
