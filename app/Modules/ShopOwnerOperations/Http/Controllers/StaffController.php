<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Models\ShopMembership;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ShopStaffInvitation;
use App\Modules\ShopOwnerOperations\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff management of one shop. Routes require manage-shop (owners) for the shop in the
 * URL; invitations and memberships of other shops are not found through it.
 */
class StaffController extends Controller
{
    public function __construct(private StaffService $staff) {}

    public function index(Shop $shop): View
    {
        return view('shop-owner.staff.index', [
            'shop' => $shop,
            'members' => $this->staff->staffOf($shop),
            'invitations' => $this->staff->pendingInvitations($shop),
            'functions' => ShopFunction::cases(),
        ]);
    }

    public function invite(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:254'],
            'staff_function' => ['required', Rule::enum(ShopFunction::class)],
        ]);

        ['invitation' => $invitation, 'secret' => $secret] = $this->staff->invite($request->user(), $shop, $data['email'], ShopFunction::from($data['staff_function']));

        return redirect()->route('workspace.staff.index', $shop)
            ->with('status', __('An invitation was sent to :email.', ['email' => $invitation->email]))
            ->with('invitation_link', route('staff-invitations.show', $secret));
    }

    public function revokeInvitation(Request $request, Shop $shop, ShopStaffInvitation $invitation): RedirectResponse
    {
        $this->staff->revokeInvitation($request->user(), $shop, $invitation);

        return back()->with('status', __('The invitation was revoked.'));
    }

    public function changeFunction(Request $request, Shop $shop, ShopMembership $membership): RedirectResponse
    {
        $data = $request->validate(['staff_function' => ['required', Rule::enum(ShopFunction::class)]]);

        $this->staff->changeFunction($request->user(), $shop, $membership, ShopFunction::from($data['staff_function']));

        return back()->with('status', __('The work function was changed.'));
    }

    public function remove(Request $request, Shop $shop, ShopMembership $membership): RedirectResponse
    {
        $this->staff->remove($request->user(), $shop, $membership);

        return back()->with('status', __('The staff member was removed.'));
    }
}
