<?php

namespace App\Modules\AnalyticsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Http\Controllers\Concerns\ReadsReportRequests;
use App\Modules\AnalyticsReports\Services\Reporting\CsvExporter;
use App\Modules\AnalyticsReports\Services\Reporting\ForecastService;
use App\Modules\AnalyticsReports\Services\Reporting\MetricCatalogue;
use App\Modules\AnalyticsReports\Services\Reporting\ShopDashboardService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shop owner's dashboard, its CSV export, the sales forecast prototype and the metric catalogue for
 * one shop. Routes require manage-shop for the shop in the URL; every figure is read for that shop only.
 */
class ShopInsightsController extends Controller
{
    use ReadsReportRequests;

    public function index(Request $request, Shop $shop, ShopDashboardService $dashboards): View
    {
        $period = $this->periodFor($request, $shop->loadMissing('mall')->mall);

        return view('shop-owner.insights.index', [
            'shop' => $shop,
            'period' => $period,
            'report' => $dashboards->dashboard($request->user(), $shop, $period),
        ]);
    }

    public function export(Request $request, Shop $shop, ShopDashboardService $dashboards, CsvExporter $csv, AuditLogger $audit): Response
    {
        $period = $this->periodFor($request, $shop->loadMissing('mall')->mall);
        $dashboard = $dashboards->dashboard($request->user(), $shop, $period);
        $audit->record('report.exported', $shop, ['kind' => 'dashboard', 'from' => $dashboard['period']['from'], 'to' => $dashboard['period']['to']], (string) $shop->mall_id, (string) $shop->getKey());

        $content = $csv->export([
            'scope' => ReportMetric::SHOP, 'subject' => $shop->name, 'label' => __('Shop dashboard'),
            'period_from' => $dashboard['period']['from'], 'period_to' => $dashboard['period']['to'], 'timezone' => $dashboard['period']['timezone'], 'generated' => $dashboard['source_cutoff'],
        ], $dashboard);

        return $this->csvResponse($content, 'shop-dashboard-'.$shop->slug.'-'.$dashboard['period']['from'].'-to-'.$dashboard['period']['to'].'.csv');
    }

    public function forecast(Request $request, Shop $shop, ForecastService $forecasts): View
    {
        return view('analytics.forecast', [
            'title' => $shop->name,
            'back' => route('workspace.insights.index', $shop),
            'forecast' => $forecasts->forShop($request->user(), $shop),
            'catalogueRow' => ReportMetric::SalesForecast,
        ]);
    }

    public function metrics(Shop $shop, MetricCatalogue $catalogue): View
    {
        return view('analytics.metrics', [
            'title' => $shop->name,
            'back' => route('workspace.insights.index', $shop),
            'rows' => $catalogue->rows(ReportMetric::SHOP),
            'audience' => __('shop owners'),
        ]);
    }
}
