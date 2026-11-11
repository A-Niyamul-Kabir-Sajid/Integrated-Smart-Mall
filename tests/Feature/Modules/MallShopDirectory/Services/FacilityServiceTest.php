<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Enums\FacilityStatus;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FacilityService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacilityService $facilities;

    private Mall $mall;

    private Floor $ground;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facilities = app(FacilityService::class);
        $this->mall = Mall::factory()->create();
        $this->ground = Floor::factory()->for($this->mall)->create(['code' => 'G', 'name' => 'Ground Floor', 'sort_order' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return ['floor_id' => $this->ground->getKey(), 'name' => ' Washroom ', 'facility_type' => 'toilet', 'is_accessible' => true, 'location_note' => 'Near the lift', ...$overrides];
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

    public function test_a_facility_is_created_open_on_a_floor_of_the_mall_and_audited(): void
    {
        $facility = $this->facilities->create($this->mall, $this->input());

        $this->assertSame('Washroom', $facility->name);
        $this->assertSame(FacilityStatus::Open, $facility->status);
        $this->assertSame('Near the lift', $facility->location_note);
        $this->assertTrue(AuditLog::query()->where('action', 'facility.created')->where('mall_id', $this->mall->getKey())->exists());
    }

    public function test_a_floor_of_another_mall_or_an_archived_floor_is_refused(): void
    {
        $foreignFloor = Floor::factory()->create();
        $archived = Floor::factory()->for($this->mall)->create(['status' => 'archived']);

        $this->assertRule('facility.floor_in_other_mall', fn () => $this->facilities->create($this->mall, $this->input(['floor_id' => $foreignFloor->getKey()])));
        $this->assertRule('facility.floor_archived', fn () => $this->facilities->create($this->mall, $this->input(['floor_id' => $archived->getKey()])));
        $this->assertSame(0, Facility::query()->count());
    }

    public function test_a_facility_of_another_mall_cannot_be_updated_through_this_mall(): void
    {
        $foreign = Facility::factory()->create();

        $this->assertRule('facility.other_mall', fn () => $this->facilities->update($this->mall, $foreign, $this->input(['status' => 'open'])));
    }

    public function test_updating_changes_fields_and_status(): void
    {
        $facility = $this->facilities->create($this->mall, $this->input());

        $this->facilities->update($this->mall, $facility, $this->input(['name' => 'Accessible Washroom', 'status' => 'temporarily_closed', 'location_note' => '']));

        $facility->refresh();
        $this->assertSame('Accessible Washroom', $facility->name);
        $this->assertSame(FacilityStatus::TemporarilyClosed, $facility->status);
        $this->assertNull($facility->location_note);
    }

    public function test_the_finder_hides_archived_facilities_and_other_malls_and_applies_filters(): void
    {
        $first = Floor::factory()->for($this->mall)->create(['code' => 'F1', 'name' => 'First Floor', 'sort_order' => 1]);
        Facility::factory()->for($first)->create(['name' => 'Prayer Room', 'facility_type' => 'prayer_room', 'is_accessible' => false]);
        Facility::factory()->for($this->ground)->create(['name' => 'Washroom', 'facility_type' => 'toilet']);
        Facility::factory()->for($this->ground)->temporarilyClosed()->create(['name' => 'Baby Care', 'facility_type' => 'other']);
        Facility::factory()->for($this->ground)->archived()->create(['name' => 'Old Kiosk']);
        Facility::factory()->create(['name' => 'Other Mall Washroom']);

        $names = fn (array $filters = []) => $this->facilities->find($this->mall, $filters)->pluck('name')->all();

        $this->assertSame(['Baby Care', 'Washroom', 'Prayer Room'], $names());
        $this->assertSame(['Washroom'], $names(['type' => 'toilet']));
        $this->assertSame(['Prayer Room'], $names(['floor' => 'F1']));
        $this->assertSame(['Baby Care', 'Washroom'], $names(['accessible_only' => true]));
        $this->assertSame(['Washroom', 'Prayer Room'], $names(['open_only' => true]));
    }

    public function test_facilities_on_an_archived_floor_are_not_listed(): void
    {
        $basement = Floor::factory()->for($this->mall)->create(['status' => 'archived']);
        Facility::factory()->for($basement)->create();

        $this->assertSame([], $this->facilities->find($this->mall)->all());
        $this->assertCount(1, $this->facilities->allOf($this->mall));
    }
}
