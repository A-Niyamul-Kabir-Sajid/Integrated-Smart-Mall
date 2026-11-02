<?php

namespace App\Modules\AccountsAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Http\Requests\UpdateProfileRequest;
use App\Modules\AccountsAccess\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request, ProfileService $profiles): RedirectResponse
    {
        $profiles->update($request->user(), $request->safe()->only(['display_name', 'email', 'phone']));

        return redirect()->route('account.show')->with('status', __('Your profile has been updated.'));
    }
}
