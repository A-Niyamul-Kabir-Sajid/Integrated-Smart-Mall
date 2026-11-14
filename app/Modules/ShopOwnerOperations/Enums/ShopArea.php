<?php

namespace App\Modules\ShopOwnerOperations\Enums;

/**
 * A part of the shop workspace whose use depends on the member's work function.
 * The catalogue and review inbox keep the plain work-in-shop ability.
 */
enum ShopArea: string
{
    case Stock = 'stock';
    case Orders = 'orders';
    case Promotions = 'promotions';
    case Support = 'support';
    case Maintenance = 'maintenance';

    /**
     * The gate ability that guards the area, e.g. "shop-area-stock".
     */
    public function ability(): string
    {
        return 'shop-area-'.$this->value;
    }
}
