<?php

namespace App\Modules\AnalyticsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Services\ActivityReportService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A shop owner's view of their own shop's page views and view-to-order funnel. Routes require
 * manage-shop for the shop in the URL.
 */
class ShopAnalyticsController extends Controller
{
    public function index(Request $request, Shop $shop, ActivityReportService $reports): View
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'dataset' => ['nullable', Rule::enum(DatasetLabel::class)],
        ]);

        $shop->loadMissing('mall');
        $period = ReportPeriod::fromInput($shop->mall, $input['from'] ?? null, $input['to'] ?? null);
        $label = DatasetLabel::tryFrom((string) ($input['dataset'] ?? '')) ?? DatasetLabel::Real;

        return view('shop-owner.analytics.index', [
            'shop' => $shop,
            'period' => $period,
            'label' => $label,
            'report' => $reports->shopAnalytics($request->user(), $shop, $period, $label),
        ]);
    }
}
