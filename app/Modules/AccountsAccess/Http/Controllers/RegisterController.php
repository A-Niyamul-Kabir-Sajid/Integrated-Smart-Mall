<?php

namespace App\Modules\AccountsAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AccountsAccess\Http\Requests\RegisterUserRequest;
use App\Modules\AccountsAccess\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterUserRequest $request, RegistrationService $registration): RedirectResponse
    {
        $user = $registration->registerCustomer($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('account.show')
            ->with('status', __('Welcome, :name! Your account is ready.', ['name' => $user->display_name]));
    }
}
