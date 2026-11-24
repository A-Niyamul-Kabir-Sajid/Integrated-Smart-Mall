<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\CheckpointController;
use App\Http\Controllers\Admin\ClosureController;
use App\Http\Controllers\Admin\EndPointController;
use App\Http\Controllers\Admin\GraphController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\MapController;
use App\Http\Controllers\Admin\PathController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\EmbedController;
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
    Route::get('/maps/{mallMap}/closures', [ClosureController::class, 'index'])->name('maps.closures');
    Route::post('/maps/{mallMap}/closures', [ClosureController::class, 'update'])->name('maps.closures.update');
    Route::get('/maps/{mallMap}/end-points', [EndPointController::class, 'index'])->name('maps.end-points');
    Route::post('/maps/{mallMap}/end-points', [EndPointController::class, 'store'])->name('maps.end-points.store');
    Route::patch('/maps/{mallMap}/end-points/{endPoint}', [EndPointController::class, 'update'])->name('maps.end-points.update');
    Route::delete('/maps/{mallMap}/end-points/{endPoint}', [EndPointController::class, 'destroy'])->name('maps.end-points.destroy');
    Route::post('/maps/{mallMap}/paths', [PathController::class, 'store'])->name('maps.paths.store');
    Route::patch('/maps/{mallMap}/paths/{path}', [PathController::class, 'update'])->name('maps.paths.update');
    Route::delete('/maps/{mallMap}/paths/{path}', [PathController::class, 'destroy'])->name('maps.paths.destroy');
    Route::post('/maps/{mallMap}/paths/{path}/merge', [PathController::class, 'merge'])->name('maps.paths.merge');
    Route::get('/maps/{mallMap}/graph', [GraphController::class, 'index'])->name('maps.graph');
    Route::post('/maps/{mallMap}/graph', [GraphController::class, 'store'])->name('maps.graph.store');
    Route::patch('/maps/{mallMap}/graph/{graphEdit}', [GraphController::class, 'update'])->name('maps.graph.update');
    Route::delete('/maps/{mallMap}/graph/{graphEdit}', [GraphController::class, 'destroy'])->name('maps.graph.destroy');

    Route::get('/checkpoints', [CheckpointController::class, 'index'])->name('checkpoints.index');
    Route::post('/checkpoints/{checkpoint}/retire', [CheckpointController::class, 'retire'])->name('checkpoints.retire');
    Route::post('/checkpoints/{checkpoint}/reactivate', [CheckpointController::class, 'reactivate'])->name('checkpoints.reactivate');
    Route::post('/checkpoints/{checkpoint}/replace', [CheckpointController::class, 'replace'])->name('checkpoints.replace');

    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::patch('/api-keys/{apiKey}/origins', [ApiKeyController::class, 'origins'])->name('api-keys.origins');
    Route::post('/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])->name('api-keys.revoke');
});

Route::get('/files/maps/{mallMap}/{path}', [FileController::class, 'show'])
    ->where('path', '.*')
    ->middleware('signed')
    ->name('files.show');

// Public API documentation.
Route::get('/docs', DocsController::class)->name('docs');

// The embedded map: the page opens from a signed link created through POST /api/v1/embed-links;
// its JSON calls carry the page's session token.
Route::get('/embed/maps/{mallMap}', [EmbedController::class, 'show'])->middleware('signed')->name('embed.show');
Route::middleware(['embed.session', 'throttle:embed'])->group(function () {
    Route::get('/embed/maps/{mallMap}/data', [EmbedController::class, 'data'])->name('embed.data');
    Route::get('/embed/maps/{mallMap}/route', [EmbedController::class, 'route'])->name('embed.route');
});
