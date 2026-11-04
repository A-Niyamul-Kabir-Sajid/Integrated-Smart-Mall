<?php

use App\Modules\FavouritesPreferences\Http\Controllers\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/account/preferences', [PreferenceController::class, 'edit'])->name('account.preferences.edit');
    Route::put('/account/preferences', [PreferenceController::class, 'update'])->name('account.preferences.update');
});
