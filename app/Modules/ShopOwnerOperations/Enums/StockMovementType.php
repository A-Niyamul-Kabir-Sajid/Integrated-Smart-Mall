<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => __('Stock received'),
            self::Adjustment => __('Stock count adjusted'),
            self::Sale => __('Sold'),
            self::Return => __('Returned to stock'),
        };
    }
}
