<?php

use App\Modules\MapsNavigation\Http\Controllers\MallMapController;
use Illuminate\Support\Facades\Route;

// The visitor's indoor map of a mall and the address printed in its QR codes.
Route::middleware('throttle:maps')->group(function (): void {
    Route::get('/malls/{publicMall}/map', [MallMapController::class, 'show'])->name('maps.show');
    Route::get('/malls/{publicMall}/map/qr/{slug}', [MallMapController::class, 'qr'])->where('slug', '[a-z0-9]{1,24}')->name('maps.qr');
});
