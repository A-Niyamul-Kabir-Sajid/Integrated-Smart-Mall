<?php

namespace App\Modules\ParkingFacilities\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ParkingFacilities\Models\ParkingZone;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Maintains the parking zones of a mall (decision D-013). A zone sits on an
 * active floor of its mall and its code is unique within the mall.
 */
class ParkingZoneService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Active zones on active floors, ordered by floor and code, for customers.
     *
     * @return Collection<int, ParkingZone>
     */
    public function activeZonesOf(Mall $mall): Collection
    {
        return $this->ordered(ParkingZone::query()->active()->where('mall_id', $mall->getKey())
            ->whereHas('floor', fn ($floor) => $floor->where('status', 'active'))->with('floor')->get());
    }

    /**
     * Every zone of the mall, archived ones included, for administrators.
     *
     * @return Collection<int, ParkingZone>
     */
    public function allZonesOf(Mall $mall): Collection
    {
        return $this->ordered(ParkingZone::query()->where('mall_id', $mall->getKey())->with('floor')->get());
    }

    /**
     * @param  array{floor_id: string, code: string, name: string, capacity: int}  $data
     */
    public function create(Mall $mall, array $data): ParkingZone
    {
        $code = $this->validCode($mall, $data['code']);

        $zone = ParkingZone::query()->create([
            'mall_id' => $mall->getKey(),
            'floor_id' => $this->activeFloorOf($mall, $data['floor_id'])->getKey(),
            'code' => $code,
            'name' => trim($data['name']),
            'capacity' => $data['capacity'],
            'status' => 'active',
        ]);

        $this->audit->record('parking_zone.created', $zone, ['code' => $code], mallId: $mall->getKey());

        return $zone;
    }

    /**
     * @param  array{floor_id: string, code: string, name: string, capacity: int, status: string}  $data
     */
    public function update(Mall $mall, ParkingZone $zone, array $data): ParkingZone
    {
        if ($zone->mall_id !== $mall->getKey()) {
            throw new BusinessRuleViolation('parking_zone.other_mall', __('This parking zone belongs to another mall.'));
        }

        $zone->fill([
            'floor_id' => $this->activeFloorOf($mall, $data['floor_id'])->getKey(),
            'code' => $this->validCode($mall, $data['code'], $zone),
            'name' => trim($data['name']),
            'capacity' => $data['capacity'],
            'status' => $data['status'],
        ]);

        $changed = array_keys($zone->getDirty());
        $zone->save();

        if ($changed !== []) {
            $this->audit->record('parking_zone.updated', $zone, ['fields' => $changed], mallId: $mall->getKey());
        }

        return $zone;
    }

    private function validCode(Mall $mall, string $code, ?ParkingZone $ignore = null): string
    {
        $code = Str::upper(trim($code));

        if (preg_match('/^[A-Z0-9-]{1,20}$/', $code) !== 1) {
            throw new BusinessRuleViolation('parking_zone.invalid_code', __('A zone code uses up to 20 letters, digits and hyphens, e.g. B1-A.'));
        }

        $taken = ParkingZone::query()->where('mall_id', $mall->getKey())->where('code', $code)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($taken) {
            throw new BusinessRuleViolation('parking_zone.code_taken', __('This mall already has a parking zone with code :code.', ['code' => $code]));
        }

        return $code;
    }

    private function activeFloorOf(Mall $mall, string $floorId): Floor
    {
        $floor = Floor::query()->find($floorId);

        if ($floor === null || $floor->mall_id !== $mall->getKey() || $floor->status !== 'active') {
            throw new BusinessRuleViolation('parking_zone.floor_unavailable', __('Choose an active floor of this mall.'));
        }

        return $floor;
    }

    /**
     * @param  Collection<int, ParkingZone>  $zones
     * @return Collection<int, ParkingZone>
     */
    private function ordered(Collection $zones): Collection
    {
        return $zones->sortBy([fn (ParkingZone $a, ParkingZone $b) => $a->floor->sort_order <=> $b->floor->sort_order, ['code', 'asc']])->values();
    }
}
