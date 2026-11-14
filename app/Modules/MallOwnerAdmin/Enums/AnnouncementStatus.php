<?php

namespace App\Modules\MallOwnerAdmin\Enums;

/**
 * A draft is visible to administrators only, a published announcement to everyone until it
 * ends, and an archived one to nobody but the administrators' list.
 */
enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Archived],
            self::Published => [self::Draft, self::Archived],
            self::Archived => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Archived => __('Archived'),
        };
    }
}
