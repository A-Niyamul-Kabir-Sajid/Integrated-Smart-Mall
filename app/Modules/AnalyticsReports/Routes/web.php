<?php

use App\Modules\AnalyticsReports\Http\Controllers\MallInsightsController;
use App\Modules\AnalyticsReports\Http\Controllers\MallReportController;
use App\Modules\AnalyticsReports\Http\Controllers\MallSearchDemandController;
use App\Modules\AnalyticsReports\Http\Controllers\MallViewsController;
use App\Modules\AnalyticsReports\Http\Controllers\ShopAnalyticsController;
use App\Modules\AnalyticsReports\Http\Controllers\ShopInsightsController;
use App\Modules\AnalyticsReports\Http\Controllers\ShopReportController;
use Illuminate\Support\Facades\Route;

// Dashboards, search demand, page views, forecast, monthly reports and exports of one mall:
// administrators of that mall (or the platform) only.
Route::middleware(['auth', 'throttle:analytics', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}/analytics')
    ->name('mall-admin.analytics.')
    ->group(function (): void {
        Route::get('/', [MallInsightsController::class, 'index'])->name('index');
        Route::get('/export', [MallInsightsController::class, 'export'])->name('export');
        Route::get('/forecast', [MallInsightsController::class, 'forecast'])->name('forecast');
        Route::get('/metrics', [MallInsightsController::class, 'metrics'])->name('metrics');
        Route::get('/search', [MallSearchDemandController::class, 'index'])->name('search');
        Route::get('/views', [MallViewsController::class, 'index'])->name('views');
        Route::get('/reports', [MallReportController::class, 'index'])->name('reports.index');
        Route::post('/reports', [MallReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}', [MallReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/csv', [MallReportController::class, 'csv'])->name('reports.csv');
    });

// One shop's own page views and funnel, dashboard, forecast, monthly reports and exports: the owner of that shop only.
Route::middleware(['auth', 'throttle:analytics', 'can:manage-shop,shop'])->group(function (): void {
    Route::get('workspace/shops/{shop}/analytics', [ShopAnalyticsController::class, 'index'])->name('workspace.analytics.index');

    Route::prefix('workspace/shops/{shop}/insights')->name('workspace.insights.')->group(function (): void {
        Route::get('/', [ShopInsightsController::class, 'index'])->name('index');
        Route::get('/export', [ShopInsightsController::class, 'export'])->name('export');
        Route::get('/forecast', [ShopInsightsController::class, 'forecast'])->name('forecast');
        Route::get('/metrics', [ShopInsightsController::class, 'metrics'])->name('metrics');
        Route::get('/reports', [ShopReportController::class, 'index'])->name('reports.index');
        Route::post('/reports', [ShopReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}', [ShopReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/csv', [ShopReportController::class, 'csv'])->name('reports.csv');
    });
});
