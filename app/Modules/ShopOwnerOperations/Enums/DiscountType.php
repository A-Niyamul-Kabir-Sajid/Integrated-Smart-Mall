<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum DiscountType: string
{
    case Percent = 'percent';
    case AmountOff = 'amount_off';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percent off the order'),
            self::AmountOff => __('Fixed amount off the order'),
        };
    }
}
