<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Services\MallAdministrationService;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The mall's own details and its opening to the public. Routes require administer-mall for
 * the mall in the URL.
 */
class MallProfileController extends Controller
{
    public function edit(Mall $mall): View
    {
        return view('mall-admin.profile.edit', ['mall' => $mall, 'timezones' => DateTimeZone::listIdentifiers()]);
    }

    public function update(Request $request, Mall $mall, MallAdministrationService $malls): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $malls->updateProfile($request->user(), $mall, $data);

        return redirect()->route('mall-admin.profile.edit', $mall)->with('status', __('The mall details were saved.'));
    }

    public function changeStatus(Request $request, Mall $mall, MallAdministrationService $malls): RedirectResponse
    {
        $to = MallStatus::from($request->validate(['status' => ['required', Rule::enum(MallStatus::class)]])['status']);

        $malls->changeStatus($request->user(), $mall, $to);

        return back()->with('status', __('The mall is now :status.', ['status' => $to->value]));
    }
}
