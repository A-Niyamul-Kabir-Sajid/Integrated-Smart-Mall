<?php

namespace App\Modules\MallShopDirectory\Enums;

/**
 * FACILITY.status: open and temporarily closed facilities are shown to
 * customers; archived ones are kept for history only.
 */
enum FacilityStatus: string
{
    case Open = 'open';
    case TemporarilyClosed = 'temporarily_closed';
    case Archived = 'archived';
}
