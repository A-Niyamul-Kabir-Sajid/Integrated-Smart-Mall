<?php

namespace App\Modules\MapsNavigation\Contracts;

use RuntimeException;

/**
 * The map service could not answer: not configured, unreachable, the key was
 * refused, or the map does not exist. The message says which; $reason is the
 * service's own reason when it gave one (no_map, retired, not_on_map,
 * unknown, unknown_place, unreachable, no_origins).
 */
class MapServiceUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }
}
