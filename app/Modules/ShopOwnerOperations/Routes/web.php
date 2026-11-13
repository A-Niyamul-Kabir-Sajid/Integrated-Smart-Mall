<?php

use App\Modules\ShopOwnerOperations\Http\Controllers\CatalogueController;
use App\Modules\ShopOwnerOperations\Http\Controllers\CatalogueImportController;
use App\Modules\ShopOwnerOperations\Http\Controllers\MallShopApplicationController;
use App\Modules\ShopOwnerOperations\Http\Controllers\ReviewController;
use App\Modules\ShopOwnerOperations\Http\Controllers\ShopApplicationController;
use App\Modules\ShopOwnerOperations\Http\Controllers\StaffController;
use App\Modules\ShopOwnerOperations\Http\Controllers\StaffInvitationController;
use App\Modules\ShopOwnerOperations\Http\Controllers\WorkspaceController;
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

Route::middleware(['auth', 'can:work-in-shop,shop'])
    ->prefix('workspace/shops/{shop}/reviews')
    ->name('workspace.reviews.')
    ->group(function (): void {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::post('/{review}/reply', [ReviewController::class, 'reply'])->middleware('throttle:review-submissions')->name('reply');
        Route::put('/replies/{reply}', [ReviewController::class, 'updateReply'])->name('replies.update');
        Route::post('/replies/{reply}/hide', [ReviewController::class, 'hideReply'])->name('replies.hide');
    });

// A customer applies to open a shop and follows the decision.
Route::middleware('auth')->group(function (): void {
    Route::get('/account/shop-applications', [ShopApplicationController::class, 'index'])->name('shop-applications.index');
    Route::get('/account/shop-applications/create', [ShopApplicationController::class, 'create'])->name('shop-applications.create');
    Route::post('/account/shop-applications', [ShopApplicationController::class, 'store'])->middleware('throttle:shop-applications')->name('shop-applications.store');

    Route::get('/staff-invitations/{secret}', [StaffInvitationController::class, 'show'])->whereAlphaNumeric('secret')->middleware('throttle:staff-invitations')->name('staff-invitations.show');
    Route::post('/staff-invitations/{secret}/accept', [StaffInvitationController::class, 'accept'])->whereAlphaNumeric('secret')->middleware('throttle:staff-invitations')->name('staff-invitations.accept');
});

// The workspace home is open to anyone who works in the shop; profile, hours and staff are for its owners.
Route::middleware(['auth', 'can:work-in-shop,shop'])->get('workspace/shops/{shop}', [WorkspaceController::class, 'dashboard'])->name('workspace.dashboard');

Route::middleware(['auth', 'can:manage-shop,shop'])
    ->prefix('workspace/shops/{shop}')
    ->name('workspace.')
    ->group(function (): void {
        Route::get('/profile', [WorkspaceController::class, 'editProfile'])->name('profile.edit');
        Route::put('/profile', [WorkspaceController::class, 'updateProfile'])->name('profile.update');
        Route::put('/hours', [WorkspaceController::class, 'updateHours'])->name('hours.update');

        Route::prefix('staff')->name('staff.')->group(function (): void {
            Route::get('/', [StaffController::class, 'index'])->name('index');
            Route::post('/invitations', [StaffController::class, 'invite'])->middleware('throttle:staff-invitations')->name('invite');
            Route::delete('/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->whereUuid('invitation')->name('invitations.revoke');
            Route::put('/{membership}', [StaffController::class, 'changeFunction'])->whereUuid('membership')->name('function');
            Route::delete('/{membership}', [StaffController::class, 'remove'])->whereUuid('membership')->name('remove');
        });
    });

// The mall's administrators decide applications and suspend or reactivate shops.
Route::middleware(['auth', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}')
    ->name('shop-applications.admin.')
    ->group(function (): void {
        Route::get('/shop-applications', [MallShopApplicationController::class, 'index'])->name('index');
        Route::post('/shop-applications/{application}/approve', [MallShopApplicationController::class, 'approve'])->whereUuid('application')->name('approve');
        Route::post('/shop-applications/{application}/reject', [MallShopApplicationController::class, 'reject'])->whereUuid('application')->name('reject');
        Route::post('/shops/{shop}/suspend', [MallShopApplicationController::class, 'suspend'])->whereUuid('shop')->name('suspend');
        Route::post('/shops/{shop}/reactivate', [MallShopApplicationController::class, 'reactivate'])->whereUuid('shop')->name('reactivate');
    });
