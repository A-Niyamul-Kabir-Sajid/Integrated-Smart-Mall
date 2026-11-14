<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum SupportStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Waiting for the shop'),
            self::Answered => __('Answered'),
            self::Closed => __('Closed'),
        };
    }
}
