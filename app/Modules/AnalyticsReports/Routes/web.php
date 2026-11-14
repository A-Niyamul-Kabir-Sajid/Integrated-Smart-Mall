<?php

use App\Modules\AnalyticsReports\Http\Controllers\MallSearchDemandController;
use App\Modules\AnalyticsReports\Http\Controllers\MallViewsController;
use App\Modules\AnalyticsReports\Http\Controllers\ShopAnalyticsController;
use Illuminate\Support\Facades\Route;

// Search demand and page views of one mall: administrators of that mall (or the platform) only.
Route::middleware(['auth', 'throttle:analytics', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}/analytics')
    ->name('mall-admin.analytics.')
    ->group(function (): void {
        Route::get('/search', [MallSearchDemandController::class, 'index'])->name('search');
        Route::get('/views', [MallViewsController::class, 'index'])->name('views');
    });

// One shop's own page views and funnel: the owner of that shop only.
Route::middleware(['auth', 'throttle:analytics', 'can:manage-shop,shop'])
    ->get('workspace/shops/{shop}/analytics', [ShopAnalyticsController::class, 'index'])
    ->name('workspace.analytics.index');
