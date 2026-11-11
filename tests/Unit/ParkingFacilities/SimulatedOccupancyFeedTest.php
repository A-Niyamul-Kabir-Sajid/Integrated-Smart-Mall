<?php

namespace Tests\Unit\ParkingFacilities;

use App\Modules\ParkingFacilities\Models\ParkingZone;
use App\Modules\ParkingFacilities\Services\SimulatedOccupancyFeed;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SimulatedOccupancyFeedTest extends TestCase
{
    private function zone(string $code, int $capacity): ParkingZone
    {
        $zone = new ParkingZone(['code' => $code, 'capacity' => $capacity]);
        $zone->parking_zone_id = $code;

        return $zone;
    }

    public function test_numbers_add_up_and_stay_within_capacity_all_day(): void
    {
        $feed = new SimulatedOccupancyFeed;
        $zones = collect([$this->zone('B1-A', 60), $this->zone('B1-B', 80), $this->zone('X', 1)]);

        foreach (range(0, 23) as $hour) {
            foreach ($feed->occupancyOf($zones, CarbonImmutable::create(2026, 11, 12, $hour)) as $numbers) {
                $this->assertSame($numbers['capacity'], $numbers['occupied'] + $numbers['free']);
                $this->assertGreaterThanOrEqual(0, $numbers['free']);
                $this->assertGreaterThanOrEqual(0, $numbers['percent']);
                $this->assertLessThanOrEqual(100, $numbers['percent']);
            }
        }
    }

    public function test_the_evening_is_busier_than_the_early_morning_and_results_repeat(): void
    {
        $feed = new SimulatedOccupancyFeed;
        $zones = collect([$this->zone('B1-A', 60)]);

        $morning = $feed->occupancyOf($zones, CarbonImmutable::create(2026, 11, 12, 6))['B1-A'];
        $evening = $feed->occupancyOf($zones, CarbonImmutable::create(2026, 11, 12, 19))['B1-A'];

        $this->assertGreaterThan($morning['occupied'], $evening['occupied']);
        $this->assertSame($evening, $feed->occupancyOf($zones, CarbonImmutable::create(2026, 11, 12, 19, 45))['B1-A']);
    }
}
