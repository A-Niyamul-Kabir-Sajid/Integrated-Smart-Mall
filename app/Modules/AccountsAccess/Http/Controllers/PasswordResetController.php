<?php

namespace App\Modules\AccountsAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\AccountSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Password reset by emailed link. Phone-only accounts need the SMS flow
 * planned for later; the form says so.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always answers the same way, so the form cannot reveal which emails have accounts.
     */
    public function email(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:254']]);

        Password::sendResetLink(['email' => Str::lower(trim($validated['email']))]);

        return back()->with('status', __('If an account uses that email, we have sent a link to reset its password.'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request, AccountSecurityService $security): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [...$validated, 'email' => Str::lower(trim($validated['email']))],
            fn (User $user, string $password) => $security->resetPassword($user, $password),
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('This password reset link is invalid or has expired.')]);
        }

        return redirect()->route('login')->with('status', __('Your password has been reset. Sign in with your new password.'));
    }
}
