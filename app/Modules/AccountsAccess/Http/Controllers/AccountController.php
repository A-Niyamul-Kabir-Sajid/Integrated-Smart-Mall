<?php

namespace App\Modules\AccountsAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'shopMemberships' => $user->shopMemberships()->active()->with(['shop', 'role'])->get()->sortBy('shop.name'),
        ]);
    }
}
