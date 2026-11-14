<?php

use App\Modules\MallOwnerAdmin\Http\Controllers\ActivityLogController;
use App\Modules\MallOwnerAdmin\Http\Controllers\CategoryAdminController;
use App\Modules\MallOwnerAdmin\Http\Controllers\FloorAdminController;
use App\Modules\MallOwnerAdmin\Http\Controllers\MallDashboardController;
use App\Modules\MallOwnerAdmin\Http\Controllers\MallProfileController;
use App\Modules\MallOwnerAdmin\Http\Controllers\ShopAdminController;
use Illuminate\Support\Facades\Route;

// Everything under /admin/malls/{mall} is for administrators of that mall (or the platform) only.
Route::middleware(['auth', 'throttle:mall-admin', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}')
    ->name('mall-admin.')
    ->group(function (): void {
        Route::get('/', [MallDashboardController::class, 'show'])->name('dashboard');

        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

        Route::get('/profile', [MallProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [MallProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/status', [MallProfileController::class, 'changeStatus'])->name('profile.status');

        Route::prefix('floors')->name('floors.')->group(function (): void {
            Route::get('/', [FloorAdminController::class, 'index'])->name('index');
            Route::post('/', [FloorAdminController::class, 'store'])->name('store');
            Route::put('/{floor}', [FloorAdminController::class, 'update'])->whereUuid('floor')->name('update');
            Route::post('/{floor}/archive', [FloorAdminController::class, 'archive'])->whereUuid('floor')->name('archive');
            Route::post('/{floor}/restore', [FloorAdminController::class, 'restore'])->whereUuid('floor')->name('restore');
        });

        Route::prefix('shops')->name('shops.')->group(function (): void {
            Route::get('/', [ShopAdminController::class, 'index'])->name('index');
            Route::get('/create', [ShopAdminController::class, 'create'])->name('create');
            Route::post('/', [ShopAdminController::class, 'store'])->name('store');
            Route::get('/{shop}/edit', [ShopAdminController::class, 'edit'])->whereUuid('shop')->name('edit');
            Route::put('/{shop}', [ShopAdminController::class, 'update'])->whereUuid('shop')->name('update');
            Route::post('/{shop}/units', [ShopAdminController::class, 'assignUnit'])->whereUuid('shop')->name('units.store');
            Route::post('/{shop}/units/{unit}/release', [ShopAdminController::class, 'releaseUnit'])->whereUuid(['shop', 'unit'])->name('units.release');
            Route::post('/{shop}/activate', [ShopAdminController::class, 'activate'])->whereUuid('shop')->name('activate');
            Route::post('/{shop}/archive', [ShopAdminController::class, 'archive'])->whereUuid('shop')->name('archive');
            Route::post('/{shop}/owners', [ShopAdminController::class, 'addOwner'])->whereUuid('shop')->name('owners.store');
            Route::post('/{shop}/owners/{owner}/remove', [ShopAdminController::class, 'removeOwner'])->whereUuid(['shop', 'owner'])->name('owners.remove');
        });
    });

// The shop category tree is shared by every mall, so only platform operators change it.
Route::middleware(['auth', 'throttle:mall-admin', 'can:administer-platform'])
    ->prefix('admin/categories')
    ->name('mall-admin.categories.')
    ->group(function (): void {
        Route::get('/', [CategoryAdminController::class, 'index'])->name('index');
        Route::post('/', [CategoryAdminController::class, 'store'])->name('store');
        Route::put('/{category}', [CategoryAdminController::class, 'update'])->whereUuid('category')->name('update');
    });
