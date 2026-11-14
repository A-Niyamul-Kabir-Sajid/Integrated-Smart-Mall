<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Enums\ComplaintCategory;
use App\Modules\MallOwnerAdmin\Models\Complaint;
use App\Modules\MallOwnerAdmin\Services\ComplaintService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A signed-in customer's own complaints to a mall. Only the customer's own complaints are
 * reachable; anyone else's is not found.
 */
class CustomerComplaintController extends Controller
{
    public function __construct(private ComplaintService $complaints) {}

    public function index(Request $request): View
    {
        return view('account.complaints.index', ['complaints' => $this->complaints->listForCustomer($request->user())]);
    }

    public function create(Request $request): View
    {
        $mall = Mall::query()->active()->where('slug', (string) $request->query('mall'))->first() ?? Mall::query()->active()->orderBy('name')->firstOrFail();

        return view('account.complaints.create', [
            'mall' => $mall,
            'malls' => Mall::query()->active()->orderBy('name')->get(),
            'shops' => $mall->shops()->whereIn('status', ['active', 'closed'])->orderBy('name')->get(['shop_id', 'name']),
            'categories' => ComplaintCategory::cases(),
            'shopId' => $request->query('shop'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mall_id' => ['required', 'uuid'],
            'shop_id' => ['nullable', 'uuid'],
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $mall = Mall::query()->active()->findOrFail($data['mall_id']);
        $shop = filled($data['shop_id'] ?? null) ? Shop::query()->findOrFail($data['shop_id']) : null;

        $complaint = $this->complaints->file($request->user(), $mall, $shop, $data);

        return redirect()->route('account.complaints.show', $complaint)->with('status', __('Your complaint :number was received.', ['number' => $complaint->complaint_number]));
    }

    public function show(Complaint $customerComplaint): View
    {
        return view('account.complaints.show', ['complaint' => $customerComplaint->load(['mall', 'shop', 'assignee', 'events.actor'])]);
    }

    public function withdraw(Request $request, Complaint $customerComplaint): RedirectResponse
    {
        $this->complaints->withdraw($request->user(), $customerComplaint);

        return back()->with('status', __('Your complaint was withdrawn.'));
    }

    public function confirm(Request $request, Complaint $customerComplaint): RedirectResponse
    {
        $this->complaints->confirm($request->user(), $customerComplaint);

        return back()->with('status', __('Thank you. The complaint is closed.'));
    }

    public function reopen(Request $request, Complaint $customerComplaint): RedirectResponse
    {
        $this->complaints->reopen($request->user(), $customerComplaint, (string) $request->input('note'));

        return back()->with('status', __('Your complaint was reopened.'));
    }
}
