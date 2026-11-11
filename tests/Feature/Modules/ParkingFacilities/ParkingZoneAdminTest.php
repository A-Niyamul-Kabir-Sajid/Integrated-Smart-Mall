<?php

namespace Tests\Feature\Modules\ParkingFacilities;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ParkingFacilities\Models\ParkingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingZoneAdminTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Floor $floor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->floor = Floor::factory()->for($this->mall)->create(['name' => 'Basement Parking']);
        $this->admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($this->admin, $this->mall, RoleCode::MallAdmin);
    }

    public function test_a_mall_administrator_adds_edits_and_archives_a_zone(): void
    {
        $this->actingAs($this->admin)->get(route('parking-zones.admin.create', $this->mall))->assertOk()->assertSee('Basement Parking');

        $this->post(route('parking-zones.admin.store', $this->mall), ['floor_id' => $this->floor->getKey(), 'code' => 'b1-a', 'name' => 'Zone A', 'capacity' => 60])
            ->assertRedirect(route('facilities.admin.index', $this->mall));
        $zone = ParkingZone::query()->sole();
        $this->assertSame('B1-A', $zone->code);
        $this->get(route('facilities.admin.index', $this->mall))->assertSee('B1-A · Zone A')->assertSee('60 spaces');

        $this->put(route('parking-zones.admin.update', [$this->mall, $zone]), ['floor_id' => $this->floor->getKey(), 'code' => 'B1-A', 'name' => 'Zone A', 'capacity' => 70, 'status' => 'archived'])
            ->assertRedirect(route('facilities.admin.index', $this->mall));
        $this->assertSame('archived', $zone->refresh()->status);
        $this->assertSame(70, $zone->capacity);
    }

    public function test_others_are_refused_and_foreign_zones_are_not_found(): void
    {
        $customer = User::factory()->create();
        $foreign = ParkingZone::factory()->create();

        $this->actingAs($customer)->get(route('parking-zones.admin.create', $this->mall))->assertForbidden();
        $this->post(route('parking-zones.admin.store', $this->mall), ['floor_id' => $this->floor->getKey(), 'code' => 'X', 'name' => 'X', 'capacity' => 1])->assertForbidden();

        $this->actingAs($this->admin)->get(route('parking-zones.admin.edit', [$this->mall, $foreign]))->assertNotFound();
        $this->post(route('parking-zones.admin.store', $this->mall), ['floor_id' => $this->floor->getKey(), 'code' => '', 'name' => '', 'capacity' => 0])
            ->assertSessionHasErrors(['code', 'name', 'capacity']);

        $this->assertSame(1, ParkingZone::query()->count());
    }
}
