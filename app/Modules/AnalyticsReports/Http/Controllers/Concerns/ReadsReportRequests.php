<?php

namespace App\Modules\AnalyticsReports\Http\Controllers\Concerns;

use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the dashboard, forecast, report and export controllers of EPIC-12 share: reading the date filter
 * and sending a CSV file.
 */
trait ReadsReportRequests
{
    private function periodFor(Request $request, Mall $mall): ReportPeriod
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return ReportPeriod::fromInput($mall, $input['from'] ?? null, $input['to'] ?? null);
    }

    private function csvResponse(string $csv, string $filename): Response
    {
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
