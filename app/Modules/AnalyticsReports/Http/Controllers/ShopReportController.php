<?php

namespace App\Modules\AnalyticsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Http\Controllers\Concerns\ReadsReportRequests;
use App\Modules\AnalyticsReports\Models\MonthlyReport;
use App\Modules\AnalyticsReports\Services\Reporting\CsvExporter;
use App\Modules\AnalyticsReports\Services\Reporting\MonthlyReportService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A shop's monthly reports: list, generate, read, download as CSV. A report is only reachable through
 * the shop it belongs to, so another shop's report answers 404 even with its id.
 */
class ShopReportController extends Controller
{
    use ReadsReportRequests;

    public function index(Request $request, Shop $shop, MonthlyReportService $reports): View
    {
        $mall = $shop->loadMissing('mall')->mall;

        return view('analytics.reports', [
            'title' => $shop->name,
            'back' => route('workspace.insights.index', $shop),
            'reports' => $reports->forShop($request->user(), $shop),
            'months' => $reports->selectableMonths($mall),
            'storeRoute' => route('workspace.insights.reports.store', $shop),
            'showRoute' => fn (MonthlyReport $report): string => route('workspace.insights.reports.show', [$shop, $report]),
        ]);
    }

    public function store(Request $request, Shop $shop, MonthlyReportService $reports): RedirectResponse
    {
        $input = $request->validate(['month' => ['required', 'string', 'max:7']]);
        $report = $reports->generateForShop($request->user(), $shop, $input['month']);

        return redirect()->route('workspace.insights.reports.show', [$shop, $report])->with('status', __('The report for :month was generated.', ['month' => $input['month']]));
    }

    public function show(Shop $shop, MonthlyReport $report): View
    {
        $this->belongsTo($shop, $report);

        return view('analytics.report', [
            'title' => $shop->name,
            'back' => route('workspace.insights.reports.index', $shop),
            'report' => $report,
            'payload' => $report->payload,
            'csvRoute' => route('workspace.insights.reports.csv', [$shop, $report]),
        ]);
    }

    public function csv(Shop $shop, MonthlyReport $report, CsvExporter $csv, AuditLogger $audit): Response
    {
        $this->belongsTo($shop, $report);
        $payload = $report->payload;
        $audit->record('report.exported', $report, ['kind' => 'monthly_report', 'month' => $payload['month']], (string) $shop->mall_id, (string) $shop->getKey());

        $content = $csv->export([
            'scope' => ReportMetric::SHOP, 'subject' => $payload['subject']['name'], 'label' => __('Monthly report :month', ['month' => $payload['month']]),
            'period_from' => $payload['period']['from'], 'period_to' => $payload['period']['to'], 'timezone' => $payload['period']['timezone'], 'generated' => $report->source_cutoff_at->toIso8601String(), 'partial' => $payload['partial'],
        ], $payload['dashboard'], $payload['previous']['metrics']);

        return $this->csvResponse($content, 'monthly-report-'.$shop->slug.'-'.$payload['month'].'.csv');
    }

    private function belongsTo(Shop $shop, MonthlyReport $report): void
    {
        abort_unless($report->scope === 'shop' && $report->subject_key === (string) $shop->getKey(), 404);
    }
}
