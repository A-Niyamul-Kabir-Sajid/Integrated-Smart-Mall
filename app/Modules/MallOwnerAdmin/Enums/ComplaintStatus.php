<?php

namespace App\Modules\MallOwnerAdmin\Enums;

/**
 * Complaint lifecycle: open, assigned to an administrator, in progress, resolved with a note,
 * then closed by the customer (or reopened). The mall can reject it with a reason and the
 * customer can withdraw it until it is resolved. Closed, rejected and withdrawn are final.
 */
enum ComplaintStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Assigned, self::Rejected, self::Withdrawn],
            self::Assigned => [self::Assigned, self::InProgress, self::Rejected, self::Withdrawn],
            self::InProgress => [self::Assigned, self::Resolved, self::Rejected, self::Withdrawn],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed, self::Rejected, self::Withdrawn => [],
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
            self::Open => __('Received'),
            self::Assigned => __('Assigned'),
            self::InProgress => __('Being looked into'),
            self::Resolved => __('Resolved, please confirm'),
            self::Closed => __('Closed'),
            self::Rejected => __('Not accepted'),
            self::Withdrawn => __('Withdrawn'),
        };
    }
}
