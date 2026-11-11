<?php

namespace App\Modules\ParkingFacilities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ParkingFacilities\Services\ParkingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Saves or clears the signed-in visitor's own parking spot in one mall. There
 * is no user id in the request: a spot can only ever be the visitor's own.
 */
class ParkingController extends Controller
{
    public function store(Request $request, Mall $publicMall, ParkingService $parking): RedirectResponse
    {
        $data = $request->validate([
            'parking_zone_id' => ['required', 'uuid'],
            'bay_label' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 -]+$/'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $parking->save($request->user()->getKey(), $publicMall, $data['parking_zone_id'], $data['bay_label'] ?? null, $data['note'] ?? null);

        return redirect()->route('facilities.index', ['publicMall' => $publicMall->slug])->withFragment('parking')
            ->with('status', __('Your parking spot was saved.'));
    }

    public function destroy(Request $request, Mall $publicMall, ParkingService $parking): RedirectResponse
    {
        $parking->clear($request->user()->getKey(), $publicMall);

        return redirect()->route('facilities.index', ['publicMall' => $publicMall->slug])->withFragment('parking')
            ->with('status', __('Your parking spot was cleared.'));
    }
}
