<?php

namespace App\Modules\ReviewsModeration\Enums;

/**
 * REVIEW.status. New reviews are published at once and policed afterwards by reports
 * and moderators; pending is kept for reviews a mall chooses to approve first. Only
 * published reviews are shown publicly and counted in rating summaries.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Hidden = 'hidden';
    case Withdrawn = 'withdrawn';

    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Published, self::Hidden, self::Withdrawn],
            self::Published => [self::Hidden, self::Withdrawn],
            self::Hidden => [self::Published],
            self::Withdrawn => [self::Published],
        };
    }
}
