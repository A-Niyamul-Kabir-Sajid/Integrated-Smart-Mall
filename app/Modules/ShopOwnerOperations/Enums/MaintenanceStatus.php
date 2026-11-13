<?php

namespace App\Modules\ShopOwnerOperations\Enums;

/**
 * Lifecycle of a maintenance request (decision D-014). The mall's administrators move it to
 * acknowledged, in progress and resolved; the shop confirms (closed) or reopens it. Closed
 * and cancelled are final.
 */
enum MaintenanceStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Acknowledged, self::Cancelled],
            self::Acknowledged => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Resolved],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed, self::Cancelled => [],
        };
    }

    public function isOpen(): bool
    {
        return $this->allowedTransitions() !== [];
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Sent to the mall'),
            self::Acknowledged => __('Acknowledged'),
            self::InProgress => __('Being fixed'),
            self::Resolved => __('Fixed, please confirm'),
            self::Closed => __('Closed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
