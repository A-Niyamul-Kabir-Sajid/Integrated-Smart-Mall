<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A route request cannot be answered. The reason is unknown_place (a start
 * or destination that is not on the map) or unreachable (no open path,
 * for example with step-free routing and the lift closed).
 */
class RouteNotPossible extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
