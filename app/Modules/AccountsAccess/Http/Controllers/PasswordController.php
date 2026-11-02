<?php

namespace App\Modules\AccountsAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Services\AccountSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    public function update(Request $request, AccountSecurityService $security): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => __('The current password is incorrect.'),
            'password.different' => __('Choose a password different from your current one.'),
        ]);

        $security->changePassword($request->user(), $validated['password'], $request->session()->getId());

        return redirect()->route('account.show')->with('status', __('Your password has been changed. Other devices were signed out.'));
    }
}
