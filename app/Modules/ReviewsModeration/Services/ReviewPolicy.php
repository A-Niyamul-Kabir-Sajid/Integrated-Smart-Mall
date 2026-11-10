<?php

namespace App\Modules\ReviewsModeration\Services;

use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\Review;
use Carbon\CarbonImmutable;

/**
 * The review rules that are policy choices rather than data constraints, in one place.
 *
 * - A new review is published at once; reports and moderators police it afterwards.
 * - The author may change a published or pending review for 30 days after writing it.
 *   A withdrawn review can be written again at any time (it reuses the same row).
 *   A hidden review is under moderation and cannot be changed by its author.
 * - One review per customer per target; staff and owners of a shop cannot review that shop
 *   or its products (the conflict-of-interest policy the ERD asks to define before release).
 */
final class ReviewPolicy
{
    public const EDIT_WINDOW_DAYS = 30;

    public const TITLE_MAX = 160;

    public const BODY_MIN = 10;

    public const BODY_MAX = 2000;

    public const REPLY_MAX = 1000;

    public const REPORT_DETAILS_MAX = 1000;

    public const REASON_MAX = 1000;

    public static function editDeadline(Review $review): CarbonImmutable
    {
        return CarbonImmutable::instance($review->created_at)->addDays(self::EDIT_WINDOW_DAYS);
    }

    /**
     * Whether the author may change the review's content now.
     */
    public static function authorMayEdit(Review $review): bool
    {
        return match ($review->status) {
            ReviewStatus::Withdrawn => true,
            ReviewStatus::Pending, ReviewStatus::Published => self::editDeadline($review)->isFuture(),
            ReviewStatus::Hidden => false,
        };
    }
}
