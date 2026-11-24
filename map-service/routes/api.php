<?php

use App\Http\Controllers\Api\AnnotationController;
use App\Http\Controllers\Api\MapController;
use Illuminate\Support\Facades\Route;

/*
| Version 1 of the map service API. Every request needs an API key
| (Authorization: Bearer ism_... or X-Api-Key: ism_...) with the ability named
| on its group. Keys are created on /admin/api-keys or with php artisan api-key:create.
*/

Route::prefix('v1')->middleware('throttle:api-key')->name('api.')->group(function () {
    Route::middleware('api.key:maps:read')->group(function () {
        Route::get('/maps', [MapController::class, 'index'])->name('maps.index');
        Route::get('/maps/{mallMap}', [MapController::class, 'show'])->name('maps.show');
        Route::get('/maps/{mallMap}/annotations', [AnnotationController::class, 'index'])->name('annotations.index');
        Route::get('/maps/{mallMap}/annotations/{code}', [AnnotationController::class, 'show'])->name('annotations.show');
    });

    Route::middleware('api.key:annotations:write')->group(function () {
        Route::post('/maps/{mallMap}/annotations', [AnnotationController::class, 'store'])->name('annotations.store');
        Route::put('/maps/{mallMap}/annotations/{code}', [AnnotationController::class, 'update'])->name('annotations.update');
        Route::delete('/maps/{mallMap}/annotations/{code}', [AnnotationController::class, 'destroy'])->name('annotations.destroy');
    });
});
