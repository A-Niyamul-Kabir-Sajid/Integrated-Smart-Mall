<?php

namespace Tests\Feature\Modules\ParkingFacilities;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ParkingFacilities\Models\ParkingLocation;
use App\Modules\ParkingFacilities\Models\ParkingZone;
use App\Modules\ParkingFacilities\Services\ParkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityFinderTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Floor $basement;

    private Floor $ground;

    private ParkingZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['slug' => 'demo-mall']);
        $this->basement = Floor::factory()->for($this->mall)->create(['code' => 'B1', 'name' => 'Basement Parking', 'sort_order' => -1]);
        $this->ground = Floor::factory()->for($this->mall)->create(['code' => 'G', 'name' => 'Ground Floor', 'sort_order' => 0]);
        Facility::factory()->for($this->ground)->create(['name' => 'Washroom', 'facility_type' => 'toilet', 'location_note' => 'Behind the Food Court']);
        Facility::factory()->for($this->ground)->create(['name' => 'Lift A', 'facility_type' => 'lift']);
        Facility::factory()->for($this->ground)->temporarilyClosed()->create(['name' => 'Baby Care', 'facility_type' => 'other']);
        Facility::factory()->for($this->ground)->archived()->create(['name' => 'Old Kiosk']);
        $this->zone = ParkingZone::factory()->for($this->basement)->create(['code' => 'B1-A', 'name' => 'Zone A', 'capacity' => 60]);
    }

    private function url(array $query = []): string
    {
        return route('facilities.index', ['publicMall' => 'demo-mall', ...$query]);
    }

    public function test_anyone_can_find_listed_facilities_by_floor(): void
    {
        $this->get($this->url())
            ->assertOk()
            ->assertSeeInOrder(['Ground Floor', 'Baby Care', 'Temporarily closed', 'Lift A', 'Washroom', 'Behind the Food Court'])
            ->assertDontSee('Old Kiosk')
            ->assertSee('Sign in to remember where you parked.');
    }

    public function test_filters_narrow_the_list_and_bad_filters_are_refused(): void
    {
        $this->get($this->url(['type' => 'toilet']))->assertSee('Washroom')->assertDontSee('Lift A');
        $this->get($this->url(['open_only' => 1]))->assertDontSee('Baby Care');
        $this->get($this->url(['floor' => 'B1']))->assertSee('No facility matches these filters.');
        $this->get($this->url(['type' => 'casino']))->assertSessionHasErrors('type');
    }

    public function test_occupancy_is_always_labelled_as_simulated(): void
    {
        $this->get($this->url())
            ->assertSee('Simulated data, not live')
            ->assertSee('role="meter"', false)
            ->assertSee('B1-A · Zone A');
    }

    public function test_the_page_is_labelled_for_assistive_technology(): void
    {
        $this->get($this->url())
            ->assertSee('<label for="facility-type"', false)
            ->assertSee('<label for="facility-floor"', false)
            ->assertSee('aria-labelledby="floor-G-heading"', false)
            ->assertSee('aria-label="Filter facilities"', false);
    }

    public function test_a_customer_saves_a_spot_and_gets_directions_back(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('parking.store', ['publicMall' => 'demo-mall']), ['parking_zone_id' => $this->zone->getKey(), 'bay_label' => '14', 'note' => 'Blue car'])
            ->assertRedirect($this->url().'#parking');

        $this->get($this->url(['from' => 'G']))
            ->assertSee('You parked in B1-A · Zone A on Basement Parking, bay 14')
            ->assertSee('Blue car')
            ->assertSeeInOrder(['Go to Lift A.', 'Go down 1 floor to Basement Parking.', 'Your car is in zone B1-A (Zone A), bay 14.']);

        $this->delete(route('parking.destroy', ['publicMall' => 'demo-mall']))->assertRedirect($this->url().'#parking');
        $this->get($this->url())->assertSee('No spot saved for this mall yet.');
    }

    public function test_a_spot_is_private_and_foreign_zones_are_refused(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        app(ParkingService::class)->save($other->getKey(), $this->mall, $this->zone->getKey(), '99', 'Secret note');

        $this->actingAs($me)->get($this->url())->assertDontSee('Secret note')->assertSee('No spot saved for this mall yet.');

        $this->from($this->url())
            ->post(route('parking.store', ['publicMall' => 'demo-mall']), ['parking_zone_id' => ParkingZone::factory()->create()->getKey()])
            ->assertSessionHasErrors('business');
        $this->post(route('parking.store', ['publicMall' => 'demo-mall']), ['parking_zone_id' => $this->zone->getKey(), 'bay_label' => '<script>'])
            ->assertSessionHasErrors('bay_label');

        $this->assertSame(1, ParkingLocation::query()->count());
    }

    public function test_guests_cannot_save_a_spot(): void
    {
        $this->post(route('parking.store', ['publicMall' => 'demo-mall']), ['parking_zone_id' => $this->zone->getKey()])->assertRedirect(route('login'));
        $this->assertSame(0, ParkingLocation::query()->count());
    }

    public function test_the_header_links_to_the_finder(): void
    {
        $this->get(route('malls.show', ['publicMall' => 'demo-mall']))->assertSee('href="'.$this->url().'"', false);
    }
}
