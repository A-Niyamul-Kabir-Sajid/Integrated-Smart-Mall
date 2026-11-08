<?php

namespace App\Modules\ReviewsModeration\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\PurchaseVerification\Events\PurchaseStatusChanged;
use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\Listeners\RevokeVerificationWhenPurchaseEnds;
use App\Modules\ReviewsModeration\Services\RatingSummaryService;

/**
 * Registers ReviewsModeration: reviews, ratings, shop replies, reports and moderation.
 */
class ReviewsModerationServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [
        PurchaseStatusChanged::class => [RevokeVerificationWhenPurchaseEnds::class],
    ];

    public function register(): void
    {
        $this->app->bind(ReviewSummaryLookup::class, RatingSummaryService::class);
    }
}
