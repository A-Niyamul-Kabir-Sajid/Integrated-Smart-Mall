<?php

namespace App\Modules\MallOwnerAdmin\Enums;

/**
 * Work order lifecycle: open, assigned to a mall administrator, in progress, completed with a
 * note. It can be cancelled until it is completed; reassigning keeps it assigned.
 */
enum WorkOrderStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Assigned, self::Cancelled],
            self::Assigned => [self::Assigned, self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function isOpen(): bool
    {
        return $this->allowedTransitions() !== [];
    }

    /**
     * @return list<self>
     */
    public static function openCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Assigned => __('Assigned'),
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
