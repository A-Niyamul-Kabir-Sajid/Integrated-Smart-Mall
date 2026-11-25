<?php

use App\Http\Controllers\Api\AnnotationController;
use App\Http\Controllers\Api\CheckpointController;
use App\Http\Controllers\Api\EdgeController;
use App\Http\Controllers\Api\EmbedLinkController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\RouteController;
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
        Route::get('/maps/{mallMap}/places', [PlaceController::class, 'places'])->name('maps.places');
        Route::match(['get', 'post'], '/maps/{mallMap}/routes', [RouteController::class, 'forMap'])->name('maps.routes');
        Route::get('/maps/{mallMap}/closures', [EdgeController::class, 'closed'])->name('maps.closures');
        Route::get('/malls/{mallRef}', [PlaceController::class, 'mall'])->where('mallRef', '[a-z0-9][a-z0-9-]*')->name('malls.show');
        Route::match(['get', 'post'], '/malls/{mallRef}/routes', [RouteController::class, 'forMall'])->where('mallRef', '[a-z0-9][a-z0-9-]*')->name('malls.routes');
        Route::post('/embed-links', [EmbedLinkController::class, 'store'])->name('embed-links.store');
        Route::get('/checkpoints/{slug}', [CheckpointController::class, 'show'])->where('slug', '[a-z0-9]{1,24}')->name('checkpoints.show');
    });

    Route::middleware('api.key:annotations:write')->group(function () {
        Route::post('/maps/{mallMap}/annotations', [AnnotationController::class, 'store'])->name('annotations.store');
        Route::put('/maps/{mallMap}/annotations/{code}', [AnnotationController::class, 'update'])->name('annotations.update');
        Route::delete('/maps/{mallMap}/annotations/{code}', [AnnotationController::class, 'destroy'])->name('annotations.destroy');
    });

    Route::middleware('api.key:graph:write')->group(function () {
        Route::patch('/maps/{mallMap}/edges', [EdgeController::class, 'update'])->name('edges.update');
    });
});
