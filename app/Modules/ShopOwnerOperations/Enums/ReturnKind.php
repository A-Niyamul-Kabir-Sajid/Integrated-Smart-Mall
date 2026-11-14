<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum ReturnKind: string
{
    case Return = 'return';
    case Exchange = 'exchange';

    public function label(): string
    {
        return match ($this) {
            self::Return => __('Return for a refund'),
            self::Exchange => __('Exchange for other goods'),
        };
    }
}
