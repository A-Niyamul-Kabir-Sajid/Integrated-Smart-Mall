<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A scanned QR code cannot be used as a start point. The reason is one of
 * unknown, retired, no_map or not_on_map; the HTTP status goes with it.
 */
class CheckpointUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}
