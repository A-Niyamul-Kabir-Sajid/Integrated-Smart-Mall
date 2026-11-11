<?php

namespace App\Modules\ParkingFacilities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ParkingFacilities\Models\ParkingZone;
use App\Modules\ParkingFacilities\Services\ParkingZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Parking-zone maintenance for one mall (decision D-013), next to facilities.
 */
class ParkingZoneAdminController extends Controller
{
    public function __construct(private ParkingZoneService $zones) {}

    public function create(Mall $mall): View
    {
        return view('mall-admin.parking-zones.form', $this->formData($mall, new ParkingZone(['capacity' => 50])));
    }

    public function store(Request $request, Mall $mall): RedirectResponse
    {
        $zone = $this->zones->create($mall, $this->validated($request, isNew: true));

        return redirect()->route('facilities.admin.index', $mall)->with('status', __(':name was added.', ['name' => $zone->name]));
    }

    public function edit(Mall $mall, ParkingZone $zone): View
    {
        abort_unless($zone->mall_id === $mall->getKey(), 404);

        return view('mall-admin.parking-zones.form', $this->formData($mall, $zone));
    }

    public function update(Request $request, Mall $mall, ParkingZone $zone): RedirectResponse
    {
        abort_unless($zone->mall_id === $mall->getKey(), 404);
        $this->zones->update($mall, $zone, $this->validated($request, isNew: false));

        return redirect()->route('facilities.admin.index', $mall)->with('status', __(':name was saved.', ['name' => $zone->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $isNew): array
    {
        return $request->validate([
            'floor_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => [Rule::requiredIf(! $isNew), Rule::in(['active', 'archived'])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Mall $mall, ParkingZone $zone): array
    {
        return ['mall' => $mall, 'zone' => $zone, 'floors' => $mall->floors()->active()->get()];
    }
}
