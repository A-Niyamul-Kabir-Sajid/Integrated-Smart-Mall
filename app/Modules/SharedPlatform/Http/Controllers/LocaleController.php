<?php

namespace App\Modules\SharedPlatform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SharedPlatform\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request, LocaleService $locales): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(LocaleService::SUPPORTED)],
        ]);

        $locales->remember($request->session(), $validated['locale']);

        return back();
    }
}
