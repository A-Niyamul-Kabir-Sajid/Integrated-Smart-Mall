<?php

use App\Modules\MallShopDirectory\Http\Controllers\MallController;
use App\Modules\MallShopDirectory\Http\Controllers\ShopDirectoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MallController::class, 'index'])->name('home');

Route::prefix('malls/{publicMall}')->group(function (): void {
    Route::get('/', [MallController::class, 'show'])->name('malls.show');
    Route::get('/shops', [ShopDirectoryController::class, 'index'])->middleware('throttle:search')->name('shops.index');
    Route::get('/shops/{publicShop}', [ShopDirectoryController::class, 'show'])->name('shops.show');
});
