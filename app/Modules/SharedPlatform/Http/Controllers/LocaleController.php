<?php

namespace App\Modules\SharedPlatform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SharedPlatform\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(SetLocale::SUPPORTED_LOCALES)],
        ]);

        $request->session()->put(SetLocale::SESSION_KEY, $validated['locale']);

        return back();
    }
}
