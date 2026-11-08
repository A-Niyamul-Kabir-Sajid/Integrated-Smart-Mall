<?php

namespace App\Modules\PurchaseVerification\Enums;

/**
 * PURCHASE.status. A recorded sale is completed; later it may be voided (entered
 * by mistake) or refunded. Voided and refunded are final and keep the history.
 */
enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Voided = 'voided';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Completed, self::Voided],
            self::Completed => [self::Voided, self::Refunded],
            self::Voided, self::Refunded => [],
        };
    }

    /**
     * Whether the sale is final enough that its amounts and lines may no longer change.
     */
    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }
}
