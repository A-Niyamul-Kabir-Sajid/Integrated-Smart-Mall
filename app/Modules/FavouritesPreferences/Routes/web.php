<?php

use App\Modules\FavouritesPreferences\Http\Controllers\FavouriteController;
use App\Modules\FavouritesPreferences\Http\Controllers\PreferenceController;
use App\Modules\FavouritesPreferences\Http\Controllers\ShoppingListController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/account/preferences', [PreferenceController::class, 'edit'])->name('account.preferences.edit');
    Route::put('/account/preferences', [PreferenceController::class, 'update'])->name('account.preferences.update');

    Route::get('/account/saved', [FavouriteController::class, 'index'])->name('account.saved');

    Route::get('/account/lists', [ShoppingListController::class, 'index'])->name('account.lists.index');
    Route::get('/account/lists/{list}', [ShoppingListController::class, 'show'])->whereUuid('list')->name('account.lists.show');

    // Saving favourites and editing lists share one per-account rate limit.
    Route::middleware('throttle:favourites')->group(function (): void {
        Route::post('/account/lists', [ShoppingListController::class, 'store'])->name('account.lists.store');
        Route::put('/account/lists/{list}', [ShoppingListController::class, 'update'])->whereUuid('list')->name('account.lists.update');
        Route::delete('/account/lists/{list}', [ShoppingListController::class, 'destroy'])->whereUuid('list')->name('account.lists.destroy');
        Route::post('/account/lists/{list}/items', [ShoppingListController::class, 'storeItem'])->whereUuid('list')->name('account.lists.items.store');
        Route::patch('/account/lists/{list}/items/{item}', [ShoppingListController::class, 'updateItem'])->whereUuid(['list', 'item'])->name('account.lists.items.update');
        Route::delete('/account/lists/{list}/items/{item}', [ShoppingListController::class, 'destroyItem'])->whereUuid(['list', 'item'])->name('account.lists.items.destroy');

        Route::post('/favourites/shops/{shop}', [FavouriteController::class, 'storeShop'])->whereUuid('shop')->name('favourites.shops.store');
        Route::delete('/favourites/shops/{shop}', [FavouriteController::class, 'destroyShop'])->whereUuid('shop')->name('favourites.shops.destroy');
        Route::post('/favourites/listings/{listing}', [FavouriteController::class, 'storeListing'])->whereUuid('listing')->name('favourites.listings.store');
        Route::delete('/favourites/listings/{listing}', [FavouriteController::class, 'destroyListing'])->whereUuid('listing')->name('favourites.listings.destroy');
    });
});
