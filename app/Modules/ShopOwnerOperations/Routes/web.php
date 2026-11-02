<?php

use App\Modules\ShopOwnerOperations\Http\Controllers\CatalogueController;
use App\Modules\ShopOwnerOperations\Http\Controllers\CatalogueImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:work-in-shop,shop'])
    ->prefix('workspace/shops/{shop}/catalogue')
    ->name('workspace.catalogue.')
    ->group(function (): void {
        Route::get('/', [CatalogueController::class, 'index'])->name('index');
        Route::get('/create', [CatalogueController::class, 'create'])->name('create');
        Route::get('/import', [CatalogueImportController::class, 'create'])->name('import.create');
        Route::post('/import/preview', [CatalogueImportController::class, 'preview'])->name('import.preview');
        Route::post('/import', [CatalogueImportController::class, 'store'])->name('import.store');
        Route::post('/', [CatalogueController::class, 'store'])->name('store');
        Route::get('/{shopListing}/edit', [CatalogueController::class, 'edit'])->name('edit');
        Route::put('/{shopListing}', [CatalogueController::class, 'update'])->name('update');
        Route::post('/{shopListing}/archive', [CatalogueController::class, 'archive'])->name('archive');
        Route::post('/{shopListing}/restore', [CatalogueController::class, 'restore'])->name('restore');
        Route::put('/{shopListing}/product', [CatalogueController::class, 'updateProduct'])->name('product.update');
        Route::post('/{shopListing}/images', [CatalogueController::class, 'storeImage'])->name('images.store');
    });
