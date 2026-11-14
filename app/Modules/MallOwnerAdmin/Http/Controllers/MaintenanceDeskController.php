<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Models\WorkOrder;
use App\Modules\MallOwnerAdmin\Services\WorkOrderService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Services\MaintenanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The mall's side of the shops' maintenance requests. Routes require administer-mall for the
 * mall in the URL; a request of another mall is not found through it.
 */
class MaintenanceDeskController extends Controller
{
    public function __construct(private MaintenanceRequestService $requests) {}

    public function index(Request $request, Mall $mall): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(MaintenanceStatus::class)]]);

        return view('mall-admin.maintenance.index', [
            'mall' => $mall,
            'requests' => $this->requests->listForMall($request->user(), $mall, MaintenanceStatus::tryFrom((string) ($filters['status'] ?? ''))),
            'statuses' => MaintenanceStatus::cases(),
            'status' => $filters['status'] ?? null,
        ]);
    }

    public function show(Mall $mall, MaintenanceRequest $adminMaintenance, WorkOrderService $workOrders, MembershipService $memberships): View
    {
        $adminMaintenance->load(['shop', 'requestedBy', 'events.actor']);

        return view('mall-admin.maintenance.show', [
            'mall' => $mall,
            'maintenance' => $adminMaintenance,
            'openOrder' => $workOrders->openOrderOf($adminMaintenance),
            'orders' => WorkOrder::query()->where('maintenance_request_id', $adminMaintenance->getKey())->latest('created_at')->get(),
            'floors' => $mall->floors()->active()->get(),
            'administrators' => $memberships->mallAdministrators($mall),
        ]);
    }

    public function acknowledge(Request $request, Mall $mall, MaintenanceRequest $adminMaintenance): RedirectResponse
    {
        $this->requests->updateStatus($request->user(), $adminMaintenance, MaintenanceStatus::Acknowledged);

        return back()->with('status', __('The request is acknowledged. The shop was told.'));
    }

    public function start(Request $request, Mall $mall, MaintenanceRequest $adminMaintenance): RedirectResponse
    {
        $this->requests->updateStatus($request->user(), $adminMaintenance, MaintenanceStatus::InProgress);

        return back()->with('status', __('The request is marked as being fixed.'));
    }

    public function resolve(Request $request, Mall $mall, MaintenanceRequest $adminMaintenance): RedirectResponse
    {
        $this->requests->updateStatus($request->user(), $adminMaintenance, MaintenanceStatus::Resolved, (string) $request->input('note'));

        return back()->with('status', __('The request is marked fixed. The shop will confirm.'));
    }
}
