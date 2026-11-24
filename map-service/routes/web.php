<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\MapController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/maps');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:6,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/maps', [MapController::class, 'index'])->name('maps.index');
    Route::post('/maps/import', [MapController::class, 'import'])->name('maps.import');
    Route::get('/maps/{mallMap}', [MapController::class, 'show'])->name('maps.show');
    Route::get('/maps/{mallMap}/package', [MapController::class, 'package'])->name('maps.package');
    Route::get('/maps/{mallMap}/annotations', [MapController::class, 'annotations'])->name('maps.annotations');
    Route::put('/maps/{mallMap}/annotations', [MapController::class, 'saveAnnotations'])->name('maps.annotations.save');
    Route::patch('/maps/{mallMap}/status', [MapController::class, 'status'])->name('maps.status');
    Route::patch('/maps/{mallMap}/mall', [MapController::class, 'mall'])->name('maps.mall');
    Route::delete('/maps/{mallMap}', [MapController::class, 'destroy'])->name('maps.destroy');

    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::post('/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])->name('api-keys.revoke');
});

Route::get('/files/maps/{mallMap}/{path}', [FileController::class, 'show'])
    ->where('path', '.*')
    ->middleware('signed')
    ->name('files.show');
