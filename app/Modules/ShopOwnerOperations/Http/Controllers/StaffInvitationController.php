<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ShopOwnerOperations\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page behind the link in a staff invitation. The secret in the URL is the only
 * credential; the page never says whether another shop or person is involved.
 */
class StaffInvitationController extends Controller
{
    public function show(Request $request, string $secret, StaffService $staff): View
    {
        $invitation = $staff->findBySecret($secret);

        return view('account.staff-invitation', [
            'secret' => $secret,
            'invitation' => $invitation?->isUsable() ? $invitation : null,
            'emailMatches' => $invitation !== null && $request->user()->email === $invitation->email,
        ]);
    }

    public function accept(Request $request, string $secret, StaffService $staff): RedirectResponse
    {
        $invitation = $staff->accept($request->user(), $secret);

        return redirect()->route('workspace.dashboard', $invitation->shop_id)->with('status', __('You now work at :shop.', ['shop' => $invitation->shop->name]));
    }
}
