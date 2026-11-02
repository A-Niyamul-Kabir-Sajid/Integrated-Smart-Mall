<?php

namespace App\Modules\MallShopDirectory\Enums;

/**
 * MALL.status. Only active malls are visible to customers; archived is final.
 */
enum MallStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Archived],
            self::Active => [self::Archived],
            self::Archived => [],
        };
    }
}
