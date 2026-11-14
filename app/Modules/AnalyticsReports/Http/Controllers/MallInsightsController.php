<?php

namespace App\Modules\AnalyticsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Http\Controllers\Concerns\ReadsReportRequests;
use App\Modules\AnalyticsReports\Services\Reporting\CsvExporter;
use App\Modules\AnalyticsReports\Services\Reporting\ForecastService;
use App\Modules\AnalyticsReports\Services\Reporting\MallDashboardService;
use App\Modules\AnalyticsReports\Services\Reporting\MetricCatalogue;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mall administrators' dashboard, its CSV export, the mall sales forecast prototype and the metric
 * catalogue. Routes require administer-mall for the mall in the URL.
 */
class MallInsightsController extends Controller
{
    use ReadsReportRequests;

    public function index(Request $request, Mall $mall, MallDashboardService $dashboards): View
    {
        $period = $this->periodFor($request, $mall);

        return view('mall-admin.analytics.dashboard', [
            'mall' => $mall,
            'period' => $period,
            'report' => $dashboards->dashboard($request->user(), $mall, $period),
        ]);
    }

    public function export(Request $request, Mall $mall, MallDashboardService $dashboards, CsvExporter $csv, AuditLogger $audit): Response
    {
        $period = $this->periodFor($request, $mall);
        $dashboard = $dashboards->dashboard($request->user(), $mall, $period);
        $audit->record('report.exported', $mall, ['kind' => 'dashboard', 'from' => $dashboard['period']['from'], 'to' => $dashboard['period']['to']], (string) $mall->getKey());

        $content = $csv->export([
            'scope' => ReportMetric::MALL, 'subject' => $mall->name, 'label' => __('Mall dashboard'),
            'period_from' => $dashboard['period']['from'], 'period_to' => $dashboard['period']['to'], 'timezone' => $dashboard['period']['timezone'], 'generated' => $dashboard['source_cutoff'],
        ], $dashboard);

        return $this->csvResponse($content, 'mall-dashboard-'.$mall->slug.'-'.$dashboard['period']['from'].'-to-'.$dashboard['period']['to'].'.csv');
    }

    public function forecast(Request $request, Mall $mall, ForecastService $forecasts): View
    {
        return view('analytics.forecast', [
            'title' => $mall->name,
            'back' => route('mall-admin.analytics.index', $mall),
            'forecast' => $forecasts->forMall($request->user(), $mall),
            'catalogueRow' => ReportMetric::SalesForecast,
        ]);
    }

    public function metrics(Mall $mall, MetricCatalogue $catalogue): View
    {
        return view('analytics.metrics', [
            'title' => $mall->name,
            'back' => route('mall-admin.analytics.index', $mall),
            'rows' => $catalogue->rows(ReportMetric::MALL),
            'audience' => __('mall administrators'),
        ]);
    }
}
