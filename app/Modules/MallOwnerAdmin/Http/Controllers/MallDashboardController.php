<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Services\MallOverviewService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * The mall administrators' home page. Routes require administer-mall for the mall in the URL.
 */
class MallDashboardController extends Controller
{
    public function show(Request $request, Mall $mall, MallOverviewService $overview): View
    {
        $summary = $overview->summary($request->user(), $mall);

        $queues = collect([
            ['route' => 'shop-applications.admin.index', 'label' => __('Shop applications'), 'count' => $summary['waiting']['applications']],
            ['route' => 'mall-admin.maintenance.index', 'label' => __('Maintenance requests'), 'count' => $summary['waiting']['maintenance']],
            ['route' => 'mall-admin.complaints.index', 'label' => __('Complaints'), 'count' => $summary['waiting']['complaints'] ?? 0],
            ['route' => 'mall-admin.work-orders.index', 'label' => __('Open work orders'), 'count' => $summary['waiting']['work_orders']],
            ['route' => 'moderation.index', 'label' => __('Reported reviews'), 'count' => $summary['waiting']['reported_reviews']],
        ])->filter(fn (array $queue): bool => Route::has($queue['route']))
            ->map(fn (array $queue): array => [...$queue, 'url' => route($queue['route'], $mall)])
            ->values();

        $tools = collect([
            ['route' => 'mall-admin.shops.index', 'label' => __('Shops and tenants'), 'hint' => __('Create, edit, place, activate and archive shops')],
            ['route' => 'mall-admin.floors.index', 'label' => __('Floors'), 'hint' => __('Floor names, order and elevation')],
            ['route' => 'facilities.admin.index', 'label' => __('Facilities and parking'), 'hint' => __('Washrooms, lifts, prayer rooms, parking zones')],
            ['route' => 'mall-admin.announcements.index', 'label' => __('Announcements and events'), 'hint' => __('Notices and events shown on the mall page')],
            ['route' => 'mall-admin.maintenance.index', 'label' => __('Maintenance requests'), 'hint' => __('Requests from shops, work orders and progress')],
            ['route' => 'mall-admin.work-orders.index', 'label' => __('Work orders'), 'hint' => __('Assign and track maintenance work')],
            ['route' => 'mall-admin.complaints.index', 'label' => __('Complaints'), 'hint' => __('Assign, work and resolve customer complaints')],
            ['route' => 'moderation.index', 'label' => __('Review moderation'), 'hint' => __('Reported and hidden reviews')],
            ['route' => 'mall-admin.analytics.index', 'label' => __('Sales and performance'), 'hint' => __('Tenant comparison, complaints, quality flags, demand')],
            ['route' => 'mall-admin.analytics.reports.index', 'label' => __('Monthly reports'), 'hint' => __('Generate and download a monthly summary')],
            ['route' => 'mall-admin.analytics.search', 'label' => __('Search demand'), 'hint' => __('What visitors search for and cannot find')],
            ['route' => 'mall-admin.analytics.views', 'label' => __('Page views'), 'hint' => __('Views of shop and product pages')],
            ['route' => 'assistant.admin.show', 'label' => __('Assistant'), 'hint' => __('Ask for figures, a report draft or the mall\'s documents')],
            ['route' => 'assistant.admin.knowledge.index', 'label' => __('Assistant knowledge'), 'hint' => __('What the assistant may quote to visitors')],
            ['route' => 'assistant.admin.activity', 'label' => __('Assistant activity'), 'hint' => __('What visitors asked and what stayed unanswered')],
            ['route' => 'mall-admin.activity.index', 'label' => __('Activity log'), 'hint' => __('Who changed what, and when')],
            ['route' => 'mall-admin.profile.edit', 'label' => __('Mall details'), 'hint' => __('Name, address, time zone and visibility')],
        ])->filter(fn (array $tool): bool => Route::has($tool['route']))
            ->map(fn (array $tool): array => [...$tool, 'url' => route($tool['route'], $mall)])
            ->values();

        return view('mall-admin.dashboard', [...$summary, 'mall' => $mall, 'queues' => $queues, 'tools' => $tools]);
    }
}
