<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Enums\ComplaintStatus;
use App\Modules\MallOwnerAdmin\Models\Complaint;
use App\Modules\MallOwnerAdmin\Services\ComplaintService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The complaints desk of one mall. Routes require administer-mall for the mall in the URL; a
 * complaint of another mall is not found through it.
 */
class ComplaintAdminController extends Controller
{
    public function __construct(private ComplaintService $complaints) {}

    public function index(Request $request, Mall $mall): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ComplaintStatus::class)],
            'mine' => ['nullable', 'boolean'],
        ]);

        return view('mall-admin.complaints.index', [
            'mall' => $mall,
            'complaints' => $this->complaints->listForMall($request->user(), $mall, $filters),
            'statuses' => ComplaintStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(Mall $mall, Complaint $adminComplaint, MembershipService $memberships): View
    {
        return view('mall-admin.complaints.show', [
            'mall' => $mall,
            'complaint' => $adminComplaint->load(['shop', 'customer', 'assignee', 'events.actor']),
            'administrators' => $memberships->mallAdministrators($mall),
        ]);
    }

    public function assign(Request $request, Mall $mall, Complaint $adminComplaint): RedirectResponse
    {
        $assignee = User::query()->findOrFail($request->validate(['assignee' => ['required', 'uuid']])['assignee']);

        $this->complaints->assign($request->user(), $adminComplaint, $assignee);

        return back()->with('status', __('The complaint is assigned to :name.', ['name' => $assignee->display_name]));
    }

    public function start(Request $request, Mall $mall, Complaint $adminComplaint): RedirectResponse
    {
        $this->complaints->start($request->user(), $adminComplaint);

        return back()->with('status', __('The complaint is being looked into.'));
    }

    public function resolve(Request $request, Mall $mall, Complaint $adminComplaint): RedirectResponse
    {
        $this->complaints->resolve($request->user(), $adminComplaint, (string) $request->input('note'));

        return back()->with('status', __('The complaint was marked resolved. The customer was told.'));
    }

    public function reject(Request $request, Mall $mall, Complaint $adminComplaint): RedirectResponse
    {
        $this->complaints->reject($request->user(), $adminComplaint, (string) $request->input('reason'));

        return back()->with('status', __('The complaint was not accepted. The customer was told why.'));
    }
}
