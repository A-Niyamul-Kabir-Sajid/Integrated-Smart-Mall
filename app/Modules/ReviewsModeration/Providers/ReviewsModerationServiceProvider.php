<?php

namespace App\Modules\ReviewsModeration\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\Services\RatingSummaryService;

/**
 * Registers ReviewsModeration: reviews, ratings, shop replies, reports and moderation.
 */
class ReviewsModerationServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReviewSummaryLookup::class, RatingSummaryService::class);
    }
}
