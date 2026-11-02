<?php

namespace App\Modules\MallShopDirectory\Enums;

/**
 * SHOP.status. Customers see active and closed shops; drafts await mall
 * approval; archived is final and keeps history referable.
 */
enum ShopStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    public function isListed(): bool
    {
        return $this === self::Active || $this === self::Closed;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Archived],
            self::Active => [self::Closed, self::Archived],
            self::Closed => [self::Active, self::Archived],
            self::Archived => [],
        };
    }
}
