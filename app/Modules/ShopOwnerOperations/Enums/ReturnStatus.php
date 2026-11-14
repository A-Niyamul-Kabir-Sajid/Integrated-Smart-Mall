<?php

namespace App\Modules\ShopOwnerOperations\Enums;

/**
 * Lifecycle of a return or exchange request (decision D-014). Rejected, completed and
 * cancelled are final.
 */
enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Completed, self::Cancelled],
            self::Rejected, self::Completed, self::Cancelled => [],
        };
    }

    public function isOpen(): bool
    {
        return $this->allowedTransitions() !== [];
    }

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Waiting for the shop'),
            self::Approved => __('Approved, bring the goods'),
            self::Rejected => __('Declined'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
