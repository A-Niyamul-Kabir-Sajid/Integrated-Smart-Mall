<?php

namespace App\Modules\ReviewsModeration\Enums;

/**
 * REVIEW_REPORT.reason_code: why a user reported a review.
 */
enum ReportReason: string
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case ConflictOfInterest = 'conflict_of_interest';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => __('Spam or advertising'),
            self::Abuse => __('Abusive or offensive'),
            self::ConflictOfInterest => __('Conflict of interest'),
            self::Other => __('Something else'),
        };
    }
}
