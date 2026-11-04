<?php

namespace App\Modules\AccountsAccess\Http\Middleware;

use App\Modules\AccountsAccess\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that was suspended or anonymised after it
 * signed in, so a status change takes effect on the next request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, __('Your account is not active.'));
            }

            return redirect()->route('login')->withErrors(['login' => __('Your account is not active. Contact the mall information desk.')]);
        }

        return $next($request);
    }
}
