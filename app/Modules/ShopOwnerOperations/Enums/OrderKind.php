<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum OrderKind: string
{
    case Reservation = 'reservation';
    case ClickAndCollect = 'click_and_collect';

    public function label(): string
    {
        return match ($this) {
            self::Reservation => __('Reserve and pay at the shop'),
            self::ClickAndCollect => __('Order ahead and collect'),
        };
    }
}
