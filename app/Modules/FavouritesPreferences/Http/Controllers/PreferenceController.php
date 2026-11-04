<?php

namespace App\Modules\FavouritesPreferences\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FavouritesPreferences\Http\Requests\UpdatePreferencesRequest;
use App\Modules\FavouritesPreferences\Services\PreferenceService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PreferenceController extends Controller
{
    public function edit(Request $request, PreferenceService $preferences): View
    {
        return view('account.preferences', [
            'preference' => $preferences->forUser($request->user()->getKey()),
            'malls' => Mall::query()->active()->orderBy('name')->get(['mall_id', 'name']),
        ]);
    }

    public function update(UpdatePreferencesRequest $request, PreferenceService $preferences, LocaleService $locales): RedirectResponse
    {
        $preference = $preferences->update($request->user()->getKey(), $request->validated());

        $locales->remember($request->session(), $preference->locale);

        return redirect()->route('account.preferences.edit')->with('status', __('Your preferences have been saved.'));
    }
}
