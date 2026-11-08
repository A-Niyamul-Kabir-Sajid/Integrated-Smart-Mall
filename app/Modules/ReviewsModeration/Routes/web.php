<?php

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
});

Route::middleware('auth')->group(function (): void {
    Route::get('/account/reviews', [ReviewController::class, 'mine'])->name('account.reviews');
});
