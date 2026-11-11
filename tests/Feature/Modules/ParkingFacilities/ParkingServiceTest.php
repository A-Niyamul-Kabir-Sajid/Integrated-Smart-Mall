<?php

namespace Tests\Feature\Modules\ParkingFacilities;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ParkingFacilities\Models\ParkingLocation;
use App\Modules\ParkingFacilities\Models\ParkingZone;
use App\Modules\ParkingFacilities\Services\ParkingService;
use App\Modules\ParkingFacilities\Services\ParkingZoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParkingService $parking;

    private Mall $mall;

    private Floor $basement;

    private Floor $ground;

    private Floor $first;

    private ParkingZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parking = app(ParkingService::class);
        $this->mall = Mall::factory()->create();
        $this->basement = Floor::factory()->for($this->mall)->create(['code' => 'B1', 'name' => 'Basement Parking', 'sort_order' => -1]);
        $this->ground = Floor::factory()->for($this->mall)->create(['code' => 'G', 'name' => 'Ground Floor', 'sort_order' => 0]);
        $this->first = Floor::factory()->for($this->mall)->create(['code' => 'F1', 'name' => 'First Floor', 'sort_order' => 1]);
        $this->zone = ParkingZone::factory()->for($this->basement)->create(['code' => 'B1-A', 'name' => 'Zone A']);
    }

    private function assertRule(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }

    public function test_saving_again_replaces_the_spot_and_clearing_removes_it(): void
    {
        $user = User::factory()->create();
        $other = ParkingZone::factory()->for($this->basement)->create(['code' => 'B1-B']);

        $this->parking->save($user->getKey(), $this->mall, $this->zone->getKey(), ' b-14 ', 'Blue car');
        $location = $this->parking->save($user->getKey(), $this->mall, $other->getKey(), 'C-2');

        $this->assertSame(1, ParkingLocation::query()->count());
        $this->assertSame('B1-B', $location->zone->code);
        $this->assertSame('C-2', $location->bay_label);
        $this->assertNull($location->note);

        $this->parking->clear($user->getKey(), $this->mall);
        $this->assertNull($this->parking->locationOf($user->getKey(), $this->mall));
    }

    public function test_spots_are_private_to_their_account(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        $this->parking->save($me->getKey(), $this->mall, $this->zone->getKey(), 'B-14');
        $this->parking->save($other->getKey(), $this->mall, $this->zone->getKey(), 'A-1');

        $this->parking->clear($other->getKey(), $this->mall);

        $this->assertSame('B-14', $this->parking->locationOf($me->getKey(), $this->mall)->bay_label);
        $this->assertNull($this->parking->locationOf($other->getKey(), $this->mall));
    }

    public function test_only_active_zones_of_the_mall_on_active_floors_can_be_saved(): void
    {
        $user = User::factory()->create()->getKey();
        $foreign = ParkingZone::factory()->create();
        $archived = ParkingZone::factory()->for($this->basement)->archived()->create();
        $onClosedFloor = ParkingZone::factory()->for(Floor::factory()->for($this->mall)->create(['status' => 'archived']))->create();

        foreach ([$foreign, $archived, $onClosedFloor] as $zone) {
            $this->assertRule('parking.zone_unavailable', fn () => $this->parking->save($user, $this->mall, $zone->getKey()));
        }

        $this->assertRule('parking.zone_unavailable', fn () => $this->parking->save($user, $this->mall, '01a1a301-0000-7000-8000-000000000000'));
        $this->assertSame(0, ParkingLocation::query()->count());
    }

    public function test_directions_back_name_the_lift_the_floors_and_the_spot(): void
    {
        Facility::factory()->for($this->first)->create(['name' => 'Lift A', 'facility_type' => 'lift', 'location_note' => 'Centre atrium']);
        $location = $this->parking->save(User::factory()->create()->getKey(), $this->mall, $this->zone->getKey(), 'B-14', 'Blue car');

        $this->assertSame([
            'Go to Lift A (Centre atrium).',
            'Go down 2 floors to Basement Parking.',
            'Your car is in zone B1-A (Zone A), bay B-14.',
            'Your note: Blue car',
        ], $this->parking->directionsBack($this->mall, $location, $this->first));

        $this->assertSame(['Your car is on this floor, in zone B1-A (Zone A), bay B-14.', 'Your note: Blue car'], $this->parking->directionsBack($this->mall, $location, $this->basement));
        $this->assertSame('Go down 1 floor to Basement Parking.', $this->parking->directionsBack($this->mall, $location, $this->ground)[0]);
    }

    public function test_deleting_an_account_deletes_its_spot(): void
    {
        $user = User::factory()->create();
        $this->parking->save($user->getKey(), $this->mall, $this->zone->getKey());

        $user->delete();

        $this->assertSame(0, ParkingLocation::query()->count());
    }

    public function test_zone_codes_are_unique_per_mall_and_zones_need_an_active_floor_of_the_mall(): void
    {
        $zones = app(ParkingZoneService::class);

        $this->assertSame('B1-C', $zones->create($this->mall, ['floor_id' => $this->basement->getKey(), 'code' => ' b1-c ', 'name' => 'Zone C', 'capacity' => 40])->code);
        $this->assertRule('parking_zone.code_taken', fn () => $zones->create($this->mall, ['floor_id' => $this->basement->getKey(), 'code' => 'B1-A', 'name' => 'Dup', 'capacity' => 1]));
        $this->assertRule('parking_zone.invalid_code', fn () => $zones->create($this->mall, ['floor_id' => $this->basement->getKey(), 'code' => 'B1 A!', 'name' => 'Bad', 'capacity' => 1]));
        $this->assertRule('parking_zone.floor_unavailable', fn () => $zones->create($this->mall, ['floor_id' => Floor::factory()->create()->getKey(), 'code' => 'X1', 'name' => 'Foreign', 'capacity' => 1]));
        $this->assertSame(['B1-A', 'B1-C'], $zones->activeZonesOf($this->mall)->pluck('code')->all());
    }
}
