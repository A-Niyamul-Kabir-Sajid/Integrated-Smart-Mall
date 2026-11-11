<?php

namespace App\Modules\MallShopDirectory\Enums;

/**
 * FACILITY.facility_type: the ERD types plus atm and lift (decision D-013).
 */
enum FacilityType: string
{
    case Toilet = 'toilet';
    case PrayerRoom = 'prayer_room';
    case Information = 'information';
    case Exit = 'exit';
    case Atm = 'atm';
    case Lift = 'lift';
    case Other = 'other';

    /**
     * The English label, translated in views with __().
     */
    public function label(): string
    {
        return match ($this) {
            self::Toilet => 'Toilet',
            self::PrayerRoom => 'Prayer room',
            self::Information => 'Information desk',
            self::Exit => 'Exit',
            self::Atm => 'ATM',
            self::Lift => 'Lift',
            self::Other => 'Other',
        };
    }
}
