<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceCategory;
use App\Modules\ShopOwnerOperations\Enums\MaintenancePriority;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Services\MaintenanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shop's maintenance requests to the mall. Routes require the maintenance area for the
 * shop in the URL, and a request of another shop is not found through it.
 */
class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceRequestService $requests) {}

    public function index(Request $request, Shop $shop): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(MaintenanceStatus::class)]])['status'] ?? null;

        return view('shop-owner.maintenance.index', [
            'shop' => $shop,
            'status' => $status,
            'requests' => $this->requests->listForShop($request->user(), $shop, $status === null ? null : MaintenanceStatus::from($status)),
            'statuses' => MaintenanceStatus::cases(),
            'categories' => MaintenanceCategory::cases(),
            'priorities' => MaintenancePriority::cases(),
        ]);
    }

    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(MaintenanceCategory::class)],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
        ]);

        $maintenance = $this->requests->create($request->user(), $shop, $data);

        return redirect()->route('workspace.maintenance.show', [$shop, $maintenance])->with('status', __('Request :number was sent to the mall.', ['number' => $maintenance->request_number]));
    }

    public function show(Shop $shop, MaintenanceRequest $shopMaintenance): View
    {
        return view('shop-owner.maintenance.show', ['shop' => $shop, 'maintenance' => $shopMaintenance->load(['events.actor:user_id,display_name', 'requestedBy:user_id,display_name'])]);
    }

    public function cancel(Request $request, Shop $shop, MaintenanceRequest $shopMaintenance): RedirectResponse
    {
        $this->requests->cancel($request->user(), $shopMaintenance->setRelation('shop', $shop));

        return back()->with('status', __('The request was cancelled.'));
    }

    public function confirm(Request $request, Shop $shop, MaintenanceRequest $shopMaintenance): RedirectResponse
    {
        $this->requests->confirm($request->user(), $shopMaintenance->setRelation('shop', $shop));

        return back()->with('status', __('Thank you. The request is closed.'));
    }

    public function reopen(Request $request, Shop $shop, MaintenanceRequest $shopMaintenance): RedirectResponse
    {
        $this->requests->reopen($request->user(), $shopMaintenance->setRelation('shop', $shop), (string) $request->input('note'));

        return back()->with('status', __('The request is back with the mall.'));
    }
}
