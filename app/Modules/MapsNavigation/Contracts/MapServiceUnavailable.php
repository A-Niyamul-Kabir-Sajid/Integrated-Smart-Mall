<?php

namespace App\Modules\MapsNavigation\Contracts;

use RuntimeException;

/**
 * The map service could not answer: not configured, unreachable, the key was
 * refused, or the map does not exist. The message says which.
 */
class MapServiceUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
