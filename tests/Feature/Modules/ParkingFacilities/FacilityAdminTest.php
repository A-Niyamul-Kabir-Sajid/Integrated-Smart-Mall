<?php

namespace Tests\Feature\Modules\ParkingFacilities;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityAdminTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Floor $floor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->floor = Floor::factory()->for($this->mall)->create(['name' => 'Ground Floor']);
        $this->admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($this->admin, $this->mall, RoleCode::MallAdmin);
    }

    public function test_a_mall_administrator_adds_and_edits_a_facility(): void
    {
        $this->actingAs($this->admin)->get(route('facilities.admin.index', $this->mall))->assertOk()->assertSee('No facilities yet.');
        $this->get(route('facilities.admin.create', $this->mall))->assertOk()->assertSee('Ground Floor');

        $this->post(route('facilities.admin.store', $this->mall), [
            'floor_id' => $this->floor->getKey(), 'name' => 'ATM Corner', 'facility_type' => 'atm', 'is_accessible' => '1', 'location_note' => 'Next to Fresh Mart',
        ])->assertRedirect(route('facilities.admin.index', $this->mall));

        $facility = Facility::query()->sole();
        $this->get(route('facilities.admin.index', $this->mall))->assertSee('ATM Corner')->assertSee('Next to Fresh Mart');

        $this->put(route('facilities.admin.update', [$this->mall, $facility]), [
            'floor_id' => $this->floor->getKey(), 'name' => 'ATM Corner', 'facility_type' => 'atm', 'status' => 'archived',
        ])->assertRedirect(route('facilities.admin.index', $this->mall));

        $this->assertSame('archived', $facility->refresh()->status->value);
        $this->assertFalse($facility->is_accessible);
    }

    public function test_customers_and_administrators_of_other_malls_are_refused(): void
    {
        $customer = User::factory()->create();
        $otherAdmin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($otherAdmin, Mall::factory()->create(), RoleCode::MallAdmin);
        $input = ['floor_id' => $this->floor->getKey(), 'name' => 'Sneaky', 'facility_type' => 'toilet'];

        foreach ([$customer, $otherAdmin] as $user) {
            $this->actingAs($user)->get(route('facilities.admin.index', $this->mall))->assertForbidden();
            $this->post(route('facilities.admin.store', $this->mall), $input)->assertForbidden();
        }

        $this->assertSame(0, Facility::query()->count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('facilities.admin.index', $this->mall))->assertRedirect(route('login'));
    }

    public function test_a_facility_of_another_mall_is_not_found_through_this_mall(): void
    {
        $foreign = Facility::factory()->create();

        $this->actingAs($this->admin)->get(route('facilities.admin.edit', [$this->mall, $foreign]))->assertNotFound();
        $this->put(route('facilities.admin.update', [$this->mall, $foreign]), ['floor_id' => $this->floor->getKey(), 'name' => 'X', 'facility_type' => 'toilet', 'status' => 'open'])->assertNotFound();
    }

    public function test_invalid_input_and_a_foreign_floor_are_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('facilities.admin.store', $this->mall), ['floor_id' => 'nope', 'name' => '', 'facility_type' => 'casino'])
            ->assertSessionHasErrors(['floor_id', 'name', 'facility_type']);

        $this->from(route('facilities.admin.create', $this->mall))
            ->post(route('facilities.admin.store', $this->mall), ['floor_id' => Floor::factory()->create()->getKey(), 'name' => 'X', 'facility_type' => 'toilet'])
            ->assertSessionHasErrors('business');

        $this->assertSame(0, Facility::query()->count());
    }
}
