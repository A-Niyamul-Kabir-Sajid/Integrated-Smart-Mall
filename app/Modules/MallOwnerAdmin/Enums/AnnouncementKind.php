<?php

namespace App\Modules\MallOwnerAdmin\Enums;

enum AnnouncementKind: string
{
    case Notice = 'notice';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Notice => __('Notice'),
            self::Event => __('Event'),
        };
    }
}
