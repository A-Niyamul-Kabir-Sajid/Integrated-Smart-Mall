<?php

namespace App\Modules\ParkingFacilities\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FacilityService;
use App\Modules\ParkingFacilities\Models\ParkingLocation;
use App\Modules\ParkingFacilities\Models\ParkingZone;

/**
 * The parking spot an account saved in a mall (decision D-013): private to
 * the account, one per mall, replaced by saving again and removed by clearing.
 */
class ParkingService
{
    public function __construct(private FacilityService $facilities) {}

    public function locationOf(string $userId, Mall $mall): ?ParkingLocation
    {
        return ParkingLocation::query()
            ->where('user_id', $userId)
            ->where('mall_id', $mall->getKey())
            ->with('zone.floor')
            ->first();
    }

    /**
     * Saves (or replaces) the account's spot. The zone must be an active zone on an
     * active floor of this mall.
     */
    public function save(string $userId, Mall $mall, string $zoneId, ?string $bayLabel = null, ?string $note = null): ParkingLocation
    {
        $zone = ParkingZone::query()->active()->where('mall_id', $mall->getKey())
            ->whereHas('floor', fn ($floor) => $floor->where('status', 'active'))
            ->find($zoneId);

        if ($zone === null) {
            throw new BusinessRuleViolation('parking.zone_unavailable', __('Choose a parking zone of this mall.'));
        }

        ParkingLocation::query()->upsert([[
            'user_id' => $userId,
            'mall_id' => $mall->getKey(),
            'parking_zone_id' => $zone->getKey(),
            'bay_label' => filled($bayLabel) ? mb_strtoupper(trim($bayLabel)) : null,
            'note' => filled($note) ? trim($note) : null,
            'saved_at' => now(),
        ]], ['user_id', 'mall_id'], ['parking_zone_id', 'bay_label', 'note', 'saved_at']);

        return $this->locationOf($userId, $mall);
    }

    public function clear(string $userId, Mall $mall): void
    {
        ParkingLocation::query()->where('user_id', $userId)->where('mall_id', $mall->getKey())->delete();
    }

    /**
     * Floor-by-floor directions from the floor the customer is on back to the saved
     * spot (D-013: MapsNavigation will replace this with a routed path). Names the
     * lifts on the starting floor when the trip changes floors.
     *
     * @return list<string>
     */
    public function directionsBack(Mall $mall, ParkingLocation $location, Floor $from): array
    {
        $zone = $location->zone;
        $target = $zone->floor;
        $spot = $location->bay_label !== null
            ? __('zone :zone (:name), bay :bay', ['zone' => $zone->code, 'name' => $zone->name, 'bay' => $location->bay_label])
            : __('zone :zone (:name)', ['zone' => $zone->code, 'name' => $zone->name]);

        $note = $location->note !== null ? [__('Your note: :note', ['note' => $location->note])] : [];

        if ($from->is($target)) {
            return [__('Your car is on this floor, in :spot.', ['spot' => $spot]), ...$note];
        }

        $levels = abs($target->sort_order - $from->sort_order);
        $steps = [];
        $lifts = $this->facilities->find($mall, ['type' => 'lift', 'floor' => $from->code, 'open_only' => true]);

        if ($lifts->isNotEmpty()) {
            $lift = $lifts->first();
            $steps[] = $lift->location_note !== null
                ? __('Go to :lift (:where).', ['lift' => $lift->name, 'where' => $lift->location_note])
                : __('Go to :lift.', ['lift' => $lift->name]);
        }

        $steps[] = $target->sort_order < $from->sort_order
            ? trans_choice('Go down :count floor to :floor.|Go down :count floors to :floor.', $levels, ['floor' => __($target->name)])
            : trans_choice('Go up :count floor to :floor.|Go up :count floors to :floor.', $levels, ['floor' => __($target->name)]);
        $steps[] = __('Your car is in :spot.', ['spot' => $spot]);

        return [...$steps, ...$note];
    }
}
