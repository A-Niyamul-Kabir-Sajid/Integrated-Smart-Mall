<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Enums\FacilityStatus;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;

/**
 * Maintains and finds the public facilities of a mall. A facility always sits
 * on an active floor of the mall it is managed for.
 */
class FacilityService
{
    /**
     * Filters accepted by find(): type, floor (a floor code) and the two switches.
     *
     * @var list<string>
     */
    public const FILTERS = ['type', 'floor', 'accessible_only', 'open_only'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{floor_id: string, name: string, facility_type: string, is_accessible: bool, location_note?: string|null}  $data
     */
    public function create(Mall $mall, array $data): Facility
    {
        $floor = $this->activeFloorOf($mall, $data['floor_id']);

        $facility = Facility::query()->create([
            'floor_id' => $floor->getKey(),
            'name' => trim($data['name']),
            'facility_type' => FacilityType::from($data['facility_type']),
            'is_accessible' => $data['is_accessible'],
            'status' => FacilityStatus::Open,
            'location_note' => filled($data['location_note'] ?? null) ? trim($data['location_note']) : null,
        ]);

        $this->audit->record('facility.created', $facility, ['type' => $facility->facility_type->value], mallId: $mall->getKey());

        return $facility;
    }

    /**
     * @param  array{floor_id: string, name: string, facility_type: string, is_accessible: bool, status: string, location_note?: string|null}  $data
     */
    public function update(Mall $mall, Facility $facility, array $data): Facility
    {
        $this->assertBelongsTo($mall, $facility);
        $floor = $this->activeFloorOf($mall, $data['floor_id']);

        $facility->fill([
            'floor_id' => $floor->getKey(),
            'name' => trim($data['name']),
            'facility_type' => FacilityType::from($data['facility_type']),
            'is_accessible' => $data['is_accessible'],
            'status' => FacilityStatus::from($data['status']),
            'location_note' => filled($data['location_note'] ?? null) ? trim($data['location_note']) : null,
        ]);

        $changed = array_keys($facility->getDirty());
        $facility->save();

        if ($changed !== []) {
            $this->audit->record('facility.updated', $facility, ['fields' => $changed], mallId: $mall->getKey());
        }

        return $facility;
    }

    /**
     * Every facility of the mall, archived ones included, for administrators.
     *
     * @return Collection<int, Facility>
     */
    public function allOf(Mall $mall): Collection
    {
        return Facility::query()
            ->whereHas('floor', fn ($floor) => $floor->where('mall_id', $mall->getKey()))
            ->with('floor')
            ->get()
            ->sortBy([fn (Facility $a, Facility $b) => $a->floor->sort_order <=> $b->floor->sort_order, ['name', 'asc']])
            ->values();
    }

    /**
     * Facilities customers may see on the mall's active floors, ordered by floor and name.
     *
     * @param  array{type?: string|null, floor?: string|null, accessible_only?: bool, open_only?: bool}  $filters
     * @return Collection<int, Facility>
     */
    public function find(Mall $mall, array $filters = []): Collection
    {
        return Facility::query()
            ->listed()
            ->whereHas('floor', fn ($floor) => $floor->where('mall_id', $mall->getKey())->where('status', 'active')
                ->when(filled($filters['floor'] ?? null), fn ($query) => $query->where('code', $filters['floor'])))
            ->when(filled($filters['type'] ?? null), fn ($query) => $query->where('facility_type', $filters['type']))
            ->when($filters['accessible_only'] ?? false, fn ($query) => $query->where('is_accessible', true))
            ->when($filters['open_only'] ?? false, fn ($query) => $query->where('status', FacilityStatus::Open->value))
            ->with('floor')
            ->get()
            ->sortBy([fn (Facility $a, Facility $b) => $a->floor->sort_order <=> $b->floor->sort_order, ['name', 'asc']])
            ->values();
    }

    private function activeFloorOf(Mall $mall, string $floorId): Floor
    {
        $floor = Floor::query()->find($floorId);

        if ($floor === null || $floor->mall_id !== $mall->getKey()) {
            throw new BusinessRuleViolation('facility.floor_in_other_mall', __('The floor belongs to another mall.'));
        }

        if ($floor->status !== 'active') {
            throw new BusinessRuleViolation('facility.floor_archived', __('Facilities cannot be placed on an archived floor.'));
        }

        return $floor;
    }

    private function assertBelongsTo(Mall $mall, Facility $facility): void
    {
        if ($facility->floor->mall_id !== $mall->getKey()) {
            throw new BusinessRuleViolation('facility.other_mall', __('This facility belongs to another mall.'));
        }
    }
}
