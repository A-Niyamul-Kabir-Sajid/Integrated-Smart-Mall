<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Services\MallAdministrationService;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Floors of one mall. Routes require administer-mall for the mall in the URL; a floor of
 * another mall is not found through it.
 */
class FloorAdminController extends Controller
{
    public function index(Request $request, Mall $mall, MallAdministrationService $malls): View
    {
        return view('mall-admin.floors.index', ['mall' => $mall, 'floors' => $malls->floorsOf($request->user(), $mall)]);
    }

    public function store(Request $request, Mall $mall, MallAdministrationService $malls): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'between:-100,200'],
            'elevation_m' => ['required', 'numeric', 'between:-100,500'],
        ]);

        $floor = $malls->addFloor($request->user(), $mall, $data['code'], $data['name'], (int) $data['sort_order'], (float) $data['elevation_m']);

        return back()->with('status', __('Floor :code was added.', ['code' => $floor->code]));
    }

    public function update(Request $request, Mall $mall, Floor $floor, MallAdministrationService $malls): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'between:-100,200'],
            'elevation_m' => ['required', 'numeric', 'between:-100,500'],
        ]);

        $malls->updateFloor($request->user(), $mall, $floor, $data['name'], (int) $data['sort_order'], (float) $data['elevation_m']);

        return back()->with('status', __('Floor :code was saved.', ['code' => $floor->code]));
    }

    public function archive(Request $request, Mall $mall, Floor $floor, MallAdministrationService $malls): RedirectResponse
    {
        $malls->archiveFloor($request->user(), $mall, $floor);

        return back()->with('status', __('Floor :code was archived.', ['code' => $floor->code]));
    }

    public function restore(Request $request, Mall $mall, Floor $floor, MallAdministrationService $malls): RedirectResponse
    {
        $malls->restoreFloor($request->user(), $mall, $floor);

        return back()->with('status', __('Floor :code is active again.', ['code' => $floor->code]));
    }
}
