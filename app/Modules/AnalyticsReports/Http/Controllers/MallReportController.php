<?php

namespace App\Modules\AnalyticsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Http\Controllers\Concerns\ReadsReportRequests;
use App\Modules\AnalyticsReports\Models\MonthlyReport;
use App\Modules\AnalyticsReports\Services\Reporting\CsvExporter;
use App\Modules\AnalyticsReports\Services\Reporting\MonthlyReportService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A mall's monthly reports: list, generate, read, download as CSV. A report is only reachable through
 * the mall it belongs to; shop reports are never reachable here.
 */
class MallReportController extends Controller
{
    use ReadsReportRequests;

    public function index(Request $request, Mall $mall, MonthlyReportService $reports): View
    {
        return view('analytics.reports', [
            'title' => $mall->name,
            'back' => route('mall-admin.analytics.index', $mall),
            'reports' => $reports->forMall($request->user(), $mall),
            'months' => $reports->selectableMonths($mall),
            'storeRoute' => route('mall-admin.analytics.reports.store', $mall),
            'showRoute' => fn (MonthlyReport $report): string => route('mall-admin.analytics.reports.show', [$mall, $report]),
        ]);
    }

    public function store(Request $request, Mall $mall, MonthlyReportService $reports): RedirectResponse
    {
        $input = $request->validate(['month' => ['required', 'string', 'max:7']]);
        $report = $reports->generateForMall($request->user(), $mall, $input['month']);

        return redirect()->route('mall-admin.analytics.reports.show', [$mall, $report])->with('status', __('The report for :month was generated.', ['month' => $input['month']]));
    }

    public function show(Mall $mall, MonthlyReport $report): View
    {
        $this->belongsTo($mall, $report);

        return view('analytics.report', [
            'title' => $mall->name,
            'back' => route('mall-admin.analytics.reports.index', $mall),
            'report' => $report,
            'payload' => $report->payload,
            'csvRoute' => route('mall-admin.analytics.reports.csv', [$mall, $report]),
        ]);
    }

    public function csv(Mall $mall, MonthlyReport $report, CsvExporter $csv, AuditLogger $audit): Response
    {
        $this->belongsTo($mall, $report);
        $payload = $report->payload;
        $audit->record('report.exported', $report, ['kind' => 'monthly_report', 'month' => $payload['month']], (string) $mall->getKey());

        $content = $csv->export([
            'scope' => ReportMetric::MALL, 'subject' => $payload['subject']['name'], 'label' => __('Monthly report :month', ['month' => $payload['month']]),
            'period_from' => $payload['period']['from'], 'period_to' => $payload['period']['to'], 'timezone' => $payload['period']['timezone'], 'generated' => $report->source_cutoff_at->toIso8601String(), 'partial' => $payload['partial'],
        ], $payload['dashboard'], $payload['previous']['metrics']);

        return $this->csvResponse($content, 'monthly-report-'.$mall->slug.'-'.$payload['month'].'.csv');
    }

    private function belongsTo(Mall $mall, MonthlyReport $report): void
    {
        abort_unless($report->scope === 'mall' && $report->subject_key === (string) $mall->getKey(), 404);
    }
}
