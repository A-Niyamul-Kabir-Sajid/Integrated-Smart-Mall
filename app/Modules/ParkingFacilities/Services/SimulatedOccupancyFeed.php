<?php

namespace App\Modules\ParkingFacilities\Services;

use App\Modules\ParkingFacilities\Models\ParkingZone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * SIMULATED parking occupancy (decision D-013, conflict C-09). There are no
 * sensors: the numbers follow a typical shopping-day curve for the hour in the
 * mall's time zone, shifted a little per zone so zones differ. Nothing is
 * stored, and every screen must label the numbers as simulated.
 */
class SimulatedOccupancyFeed
{
    /**
     * Typical share of occupied spaces (percent) for each hour of the day.
     *
     * @var array<int, int>
     */
    private const DAY_CURVE = [
        0 => 5, 1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 8, 7 => 10, 8 => 20, 9 => 30, 10 => 45, 11 => 55,
        12 => 65, 13 => 70, 14 => 65, 15 => 60, 16 => 65, 17 => 75, 18 => 85, 19 => 90, 20 => 85, 21 => 65, 22 => 40, 23 => 15,
    ];

    /**
     * @param  Collection<int, ParkingZone>  $zones
     * @return array<string, array{capacity: int, occupied: int, free: int, percent: int}>
     */
    public function occupancyOf(Collection $zones, CarbonInterface $at): array
    {
        return $zones->mapWithKeys(function (ParkingZone $zone) use ($at): array {
            $percent = max(0, min(100, self::DAY_CURVE[(int) $at->format('G')] + $this->zoneOffset($zone)));
            $occupied = (int) round($zone->capacity * $percent / 100);

            return [$zone->getKey() => [
                'capacity' => $zone->capacity,
                'occupied' => $occupied,
                'free' => $zone->capacity - $occupied,
                'percent' => $percent,
            ]];
        })->all();
    }

    /**
     * A stable shift between -5 and +5 percentage points per zone code.
     */
    private function zoneOffset(ParkingZone $zone): int
    {
        return (crc32($zone->code) % 11) - 5;
    }
}
