<?php

namespace App\Modules\ParkingFacilities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Enums\FacilityStatus;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FacilityService;
use App\Modules\ParkingFacilities\Http\Requests\FacilityRequest;
use App\Modules\ParkingFacilities\Services\ParkingZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Facility maintenance for one mall. Routes require administer-mall for the
 * mall in the URL; a facility of another mall is not found through it.
 */
class FacilityAdminController extends Controller
{
    public function __construct(private FacilityService $facilities) {}

    public function index(Mall $mall, ParkingZoneService $zones): View
    {
        return view('mall-admin.facilities.index', [
            'mall' => $mall,
            'facilities' => $this->facilities->allOf($mall),
            'zones' => $zones->allZonesOf($mall),
        ]);
    }

    public function create(Mall $mall): View
    {
        return view('mall-admin.facilities.form', $this->formData($mall, new Facility(['is_accessible' => true])));
    }

    public function store(FacilityRequest $request, Mall $mall): RedirectResponse
    {
        $facility = $this->facilities->create($mall, $request->validated());

        return redirect()->route('facilities.admin.index', $mall)->with('status', __(':name was added.', ['name' => $facility->name]));
    }

    public function edit(Mall $mall, Facility $facility): View
    {
        $this->ensureInMall($mall, $facility);

        return view('mall-admin.facilities.form', $this->formData($mall, $facility));
    }

    public function update(FacilityRequest $request, Mall $mall, Facility $facility): RedirectResponse
    {
        $this->ensureInMall($mall, $facility);
        $this->facilities->update($mall, $facility, $request->validated());

        return redirect()->route('facilities.admin.index', $mall)->with('status', __(':name was saved.', ['name' => $facility->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Mall $mall, Facility $facility): array
    {
        return [
            'mall' => $mall,
            'facility' => $facility,
            'floors' => $mall->floors()->active()->get(),
            'types' => FacilityType::cases(),
            'statuses' => FacilityStatus::cases(),
        ];
    }

    private function ensureInMall(Mall $mall, Facility $facility): void
    {
        abort_unless($facility->floor->mall_id === $mall->getKey(), 404);
    }
}
