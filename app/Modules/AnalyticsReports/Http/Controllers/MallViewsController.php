<?php

namespace App\Modules\AnalyticsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Services\ActivityReportService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Page views of every shop and product in one mall. Routes require administer-mall for the mall in
 * the URL.
 */
class MallViewsController extends Controller
{
    public function index(Request $request, Mall $mall, ActivityReportService $reports): View
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'dataset' => ['nullable', Rule::enum(DatasetLabel::class)],
        ]);

        $period = ReportPeriod::fromInput($mall, $input['from'] ?? null, $input['to'] ?? null);
        $label = DatasetLabel::tryFrom((string) ($input['dataset'] ?? '')) ?? DatasetLabel::Real;

        return view('mall-admin.analytics.views', [
            'mall' => $mall,
            'period' => $period,
            'label' => $label,
            'report' => $reports->mallViews($request->user(), $mall, $period, $label),
        ]);
    }
}
