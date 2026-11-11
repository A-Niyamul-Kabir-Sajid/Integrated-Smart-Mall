<?php

namespace App\Modules\ParkingFacilities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FacilityService;
use App\Modules\ParkingFacilities\Services\ParkingService;
use App\Modules\ParkingFacilities\Services\ParkingZoneService;
use App\Modules\ParkingFacilities\Services\SimulatedOccupancyFeed;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The customer facility finder of one mall: facilities by type and floor, the
 * car park with simulated occupancy, and the visitor's saved parking spot with
 * directions back from the floor they say they are on.
 */
class FacilityFinderController extends Controller
{
    public function index(
        Request $request,
        Mall $publicMall,
        FacilityService $facilities,
        ParkingZoneService $zones,
        ParkingService $parking,
        SimulatedOccupancyFeed $occupancy,
    ): View {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(FacilityType::class)],
            'floor' => ['nullable', 'string', 'max:20'],
            'accessible_only' => ['nullable', 'boolean'],
            'open_only' => ['nullable', 'boolean'],
            'from' => ['nullable', 'string', 'max:20'],
        ]);

        $floors = $publicMall->floors()->active()->get();
        $parkingZones = $zones->activeZonesOf($publicMall);
        $savedSpot = $request->user() === null ? null : $parking->locationOf($request->user()->getKey(), $publicMall);
        $fromFloor = filled($filters['from'] ?? null) ? $floors->firstWhere('code', $filters['from']) : null;

        return view('customer.facilities.index', [
            'mall' => $publicMall,
            'filters' => $filters,
            'floors' => $floors,
            'types' => FacilityType::cases(),
            'facilities' => $facilities->find($publicMall, [
                ...$filters,
                'accessible_only' => $request->boolean('accessible_only'),
                'open_only' => $request->boolean('open_only'),
            ])->groupBy('floor_id'),
            'parkingZones' => $parkingZones,
            'occupancy' => $occupancy->occupancyOf($parkingZones, now($publicMall->timezone)),
            'savedSpot' => $savedSpot,
            'fromFloor' => $fromFloor,
            'directions' => $savedSpot !== null && $fromFloor !== null ? $parking->directionsBack($savedSpot, $fromFloor) : [],
        ]);
    }
}
