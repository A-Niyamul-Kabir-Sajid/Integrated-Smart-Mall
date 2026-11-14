<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Enums\WorkOrderStatus;
use App\Modules\MallOwnerAdmin\Models\WorkOrder;
use App\Modules\MallOwnerAdmin\Services\WorkOrderService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ShopOwnerOperations\Enums\MaintenancePriority;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Work orders of one mall. Routes require administer-mall for the mall in the URL; a work order
 * or request of another mall is not found through it.
 */
class WorkOrderController extends Controller
{
    public function __construct(private WorkOrderService $workOrders) {}

    public function index(Request $request, Mall $mall): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(WorkOrderStatus::class)],
            'mine' => ['nullable', 'boolean'],
        ]);

        return view('mall-admin.work-orders.index', [
            'mall' => $mall,
            'orders' => $this->workOrders->listForMall($request->user(), $mall, $filters),
            'statuses' => WorkOrderStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(Mall $mall): View
    {
        return view('mall-admin.work-orders.create', ['mall' => $mall, 'floors' => $mall->floors()->active()->get(), 'priorities' => MaintenancePriority::cases()]);
    }

    public function store(Request $request, Mall $mall): RedirectResponse
    {
        $order = $this->workOrders->create($request->user(), $mall, $this->validated($request));

        return redirect()->route('mall-admin.work-orders.show', [$mall, $order])->with('status', __('Work order :number was created.', ['number' => $order->work_order_number]));
    }

    public function storeForRequest(Request $request, Mall $mall, MaintenanceRequest $adminMaintenance): RedirectResponse
    {
        $order = $this->workOrders->create($request->user(), $mall, $this->validated($request, false), $adminMaintenance);

        return redirect()->route('mall-admin.work-orders.show', [$mall, $order])->with('status', __('Work order :number was created for :request.', ['number' => $order->work_order_number, 'request' => $adminMaintenance->request_number]));
    }

    public function show(Mall $mall, WorkOrder $adminWorkOrder, MembershipService $memberships): View
    {
        return view('mall-admin.work-orders.show', [
            'mall' => $mall,
            'order' => $adminWorkOrder->load(['assignee', 'shop', 'floor', 'maintenanceRequest', 'events.actor']),
            'administrators' => $memberships->mallAdministrators($mall),
        ]);
    }

    public function assign(Request $request, Mall $mall, WorkOrder $adminWorkOrder): RedirectResponse
    {
        $data = $request->validate(['assignee' => ['required', 'uuid'], 'due_on' => ['nullable', 'date_format:Y-m-d']]);

        $this->workOrders->assign($request->user(), $adminWorkOrder, User::query()->findOrFail($data['assignee']), $data['due_on'] ?? null);

        return back()->with('status', __('The work order was assigned.'));
    }

    public function start(Request $request, Mall $mall, WorkOrder $adminWorkOrder): RedirectResponse
    {
        $this->workOrders->start($request->user(), $adminWorkOrder);

        return back()->with('status', __('Work has started.'));
    }

    public function complete(Request $request, Mall $mall, WorkOrder $adminWorkOrder): RedirectResponse
    {
        $this->workOrders->complete($request->user(), $adminWorkOrder, (string) $request->input('note'));

        return back()->with('status', __('The work order is completed.'));
    }

    public function cancel(Request $request, Mall $mall, WorkOrder $adminWorkOrder): RedirectResponse
    {
        $this->workOrders->cancel($request->user(), $adminWorkOrder, (string) $request->input('reason'));

        return back()->with('status', __('The work order was cancelled.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $titleRequired = true): array
    {
        return $request->validate([
            'title' => [$titleRequired ? 'required' : 'nullable', 'string', 'max:150'],
            'description' => [$titleRequired ? 'required' : 'nullable', 'string', 'max:2000'],
            'location_note' => ['nullable', 'string', 'max:160'],
            'floor_id' => ['nullable', 'uuid'],
            'priority' => [$titleRequired ? 'required' : 'nullable', Rule::enum(MaintenancePriority::class)],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }
}
