<?php

namespace App\Modules\ShopOwnerOperations\Enums;

/**
 * Lifecycle of a customer order (decision D-014). Completed, rejected, cancelled and
 * expired are final.
 */
enum OrderStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Ready = 'ready';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Accepted, self::Rejected, self::Cancelled, self::Expired],
            self::Accepted => [self::Ready, self::Completed, self::Rejected, self::Cancelled, self::Expired],
            self::Ready => [self::Completed, self::Rejected, self::Cancelled, self::Expired],
            self::Completed, self::Rejected, self::Cancelled, self::Expired => [],
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
            self::Accepted => __('Accepted'),
            self::Ready => __('Ready for pickup'),
            self::Completed => __('Collected'),
            self::Rejected => __('Declined by the shop'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
        };
    }

    /**
     * @return list<self>
     */
    public static function openCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }
}
