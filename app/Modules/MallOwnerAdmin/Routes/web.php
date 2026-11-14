<?php

use App\Modules\MallOwnerAdmin\Http\Controllers\ActivityLogController;
use App\Modules\MallOwnerAdmin\Http\Controllers\AnnouncementAdminController;
use App\Modules\MallOwnerAdmin\Http\Controllers\CategoryAdminController;
use App\Modules\MallOwnerAdmin\Http\Controllers\ComplaintAdminController;
use App\Modules\MallOwnerAdmin\Http\Controllers\CustomerComplaintController;
use App\Modules\MallOwnerAdmin\Http\Controllers\FloorAdminController;
use App\Modules\MallOwnerAdmin\Http\Controllers\MaintenanceDeskController;
use App\Modules\MallOwnerAdmin\Http\Controllers\MallDashboardController;
use App\Modules\MallOwnerAdmin\Http\Controllers\MallProfileController;
use App\Modules\MallOwnerAdmin\Http\Controllers\PublicAnnouncementController;
use App\Modules\MallOwnerAdmin\Http\Controllers\ShopAdminController;
use App\Modules\MallOwnerAdmin\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

// Published notices and events of a mall are public.
Route::get('/malls/{publicMall}/news', [PublicAnnouncementController::class, 'index'])->middleware('throttle:search')->name('announcements.index');

// A customer's own complaints to a mall.
Route::middleware('auth')->prefix('account/complaints')->name('account.complaints.')->group(function (): void {
    Route::get('/', [CustomerComplaintController::class, 'index'])->name('index');
    Route::get('/create', [CustomerComplaintController::class, 'create'])->name('create');
    Route::post('/', [CustomerComplaintController::class, 'store'])->middleware('throttle:complaints')->name('store');
    Route::get('/{customerComplaint}', [CustomerComplaintController::class, 'show'])->name('show');
    Route::post('/{customerComplaint}/withdraw', [CustomerComplaintController::class, 'withdraw'])->name('withdraw');
    Route::post('/{customerComplaint}/confirm', [CustomerComplaintController::class, 'confirm'])->name('confirm');
    Route::post('/{customerComplaint}/reopen', [CustomerComplaintController::class, 'reopen'])->middleware('throttle:complaints')->name('reopen');
});

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

        Route::prefix('announcements')->name('announcements.')->group(function (): void {
            Route::get('/', [AnnouncementAdminController::class, 'index'])->name('index');
            Route::get('/create', [AnnouncementAdminController::class, 'create'])->name('create');
            Route::post('/', [AnnouncementAdminController::class, 'store'])->name('store');
            Route::get('/{adminAnnouncement}/edit', [AnnouncementAdminController::class, 'edit'])->name('edit');
            Route::put('/{adminAnnouncement}', [AnnouncementAdminController::class, 'update'])->name('update');
            Route::post('/{adminAnnouncement}/publish', [AnnouncementAdminController::class, 'publish'])->name('publish');
            Route::post('/{adminAnnouncement}/unpublish', [AnnouncementAdminController::class, 'unpublish'])->name('unpublish');
            Route::post('/{adminAnnouncement}/archive', [AnnouncementAdminController::class, 'archive'])->name('archive');
        });

        Route::prefix('complaints')->name('complaints.')->group(function (): void {
            Route::get('/', [ComplaintAdminController::class, 'index'])->name('index');
            Route::get('/{adminComplaint}', [ComplaintAdminController::class, 'show'])->name('show');
            Route::post('/{adminComplaint}/assign', [ComplaintAdminController::class, 'assign'])->name('assign');
            Route::post('/{adminComplaint}/start', [ComplaintAdminController::class, 'start'])->name('start');
            Route::post('/{adminComplaint}/resolve', [ComplaintAdminController::class, 'resolve'])->name('resolve');
            Route::post('/{adminComplaint}/reject', [ComplaintAdminController::class, 'reject'])->name('reject');
        });

        Route::prefix('maintenance')->name('maintenance.')->group(function (): void {
            Route::get('/', [MaintenanceDeskController::class, 'index'])->name('index');
            Route::get('/{adminMaintenance}', [MaintenanceDeskController::class, 'show'])->name('show');
            Route::post('/{adminMaintenance}/acknowledge', [MaintenanceDeskController::class, 'acknowledge'])->name('acknowledge');
            Route::post('/{adminMaintenance}/start', [MaintenanceDeskController::class, 'start'])->name('start');
            Route::post('/{adminMaintenance}/resolve', [MaintenanceDeskController::class, 'resolve'])->name('resolve');
            Route::post('/{adminMaintenance}/work-order', [WorkOrderController::class, 'storeForRequest'])->name('work-order');
        });

        Route::prefix('work-orders')->name('work-orders.')->group(function (): void {
            Route::get('/', [WorkOrderController::class, 'index'])->name('index');
            Route::get('/create', [WorkOrderController::class, 'create'])->name('create');
            Route::post('/', [WorkOrderController::class, 'store'])->name('store');
            Route::get('/{adminWorkOrder}', [WorkOrderController::class, 'show'])->name('show');
            Route::post('/{adminWorkOrder}/assign', [WorkOrderController::class, 'assign'])->name('assign');
            Route::post('/{adminWorkOrder}/start', [WorkOrderController::class, 'start'])->name('start');
            Route::post('/{adminWorkOrder}/complete', [WorkOrderController::class, 'complete'])->name('complete');
            Route::post('/{adminWorkOrder}/cancel', [WorkOrderController::class, 'cancel'])->name('cancel');
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
