<?php

use App\Modules\ProductsCatalogue\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('malls/{publicMall}/products')->group(function (): void {
    Route::get('/', [ProductController::class, 'index'])->middleware('throttle:search')->name('products.index');
    Route::get('/compare', [ProductController::class, 'compare'])->name('products.compare');
    Route::get('/{publicProduct}', [ProductController::class, 'show'])->name('products.show');
});
