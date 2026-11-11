<?php

use App\Modules\FavouritesPreferences\Http\Controllers\FavouriteController;
use App\Modules\FavouritesPreferences\Http\Controllers\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/account/preferences', [PreferenceController::class, 'edit'])->name('account.preferences.edit');
    Route::put('/account/preferences', [PreferenceController::class, 'update'])->name('account.preferences.update');

    Route::get('/account/saved', [FavouriteController::class, 'index'])->name('account.saved');

    Route::middleware('throttle:favourites')->group(function (): void {
        Route::post('/favourites/shops/{shop}', [FavouriteController::class, 'storeShop'])->whereUuid('shop')->name('favourites.shops.store');
        Route::delete('/favourites/shops/{shop}', [FavouriteController::class, 'destroyShop'])->whereUuid('shop')->name('favourites.shops.destroy');
        Route::post('/favourites/listings/{listing}', [FavouriteController::class, 'storeListing'])->whereUuid('listing')->name('favourites.listings.store');
        Route::delete('/favourites/listings/{listing}', [FavouriteController::class, 'destroyListing'])->whereUuid('listing')->name('favourites.listings.destroy');
    });
});
