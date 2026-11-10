<?php

use App\Modules\ReviewsModeration\Http\Controllers\ModerationController;
use App\Modules\ReviewsModeration\Http\Controllers\ReportController;
use App\Modules\ReviewsModeration\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Public review pages: anyone may read the published reviews of a listed shop or an active listing.
Route::prefix('malls/{publicMall}')->group(function (): void {
    Route::get('/shops/{publicShop}/reviews', [ReviewController::class, 'shop'])->middleware('throttle:search')->name('shops.reviews.index');
    Route::get('/listings/{publicListing}/reviews', [ReviewController::class, 'listing'])->middleware('throttle:search')->name('listings.reviews.index');
});

// Writing and withdrawing reviews needs a signed-in account.
Route::middleware(['auth', 'throttle:review-submissions'])->group(function (): void {
    Route::post('/malls/{publicMall}/shops/{publicShop}/reviews', [ReviewController::class, 'storeForShop'])->name('shops.reviews.store');
    Route::post('/malls/{publicMall}/listings/{publicListing}/reviews', [ReviewController::class, 'storeForListing'])->name('listings.reviews.store');
    Route::post('/reviews/{review}/withdraw', [ReviewController::class, 'withdraw'])->name('reviews.withdraw');
    Route::post('/reviews/{review}/verify', [ReviewController::class, 'verify'])->name('reviews.verify');
    Route::post('/reviews/{review}/report', [ReportController::class, 'store'])->name('reviews.report');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/account/reviews', [ReviewController::class, 'mine'])->name('account.reviews');
});

// The moderation desk of one mall: only administrators of that mall (or the platform) may enter.
Route::middleware(['auth', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}/moderation')
    ->name('moderation.')
    ->group(function (): void {
        Route::get('/', [ModerationController::class, 'index'])->name('index');
        Route::post('/reviews/{review}/hide', [ModerationController::class, 'hide'])->name('hide');
        Route::post('/reviews/{review}/restore', [ModerationController::class, 'restore'])->name('restore');
        Route::post('/reviews/{review}/publish', [ModerationController::class, 'publish'])->name('publish');
        Route::post('/reviews/{review}/dismiss', [ModerationController::class, 'dismiss'])->name('dismiss');
        Route::post('/reviews/{review}/revoke-verification', [ModerationController::class, 'revokeVerification'])->name('revoke-verification');
        Route::post('/reports/{report}/dismiss', [ModerationController::class, 'dismissReport'])->name('reports.dismiss');
    });
