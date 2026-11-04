<?php

namespace App\Modules\AccountsAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Http\Requests\LoginRequest;
use App\Modules\AccountsAccess\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Signs in active accounts only. Unknown accounts, wrong passwords and
     * suspended or anonymised accounts all get the same message.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->credentials();

        if (! Auth::attemptWhen($credentials, fn (User $user): bool => $user->isActive(), $request->boolean('remember'))) {
            Log::notice('Sign-in failed.', ['login_type' => array_key_first($credentials)]);

            throw ValidationException::withMessages([
                'login' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();
        Log::info('Sign-in succeeded.', ['user_id' => Auth::id()]);

        return redirect()->intended(route('account.show'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', __('You have been signed out.'));
    }
}
