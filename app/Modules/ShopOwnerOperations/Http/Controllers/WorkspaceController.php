<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Services\ShopService;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\ShopApplication;
use App\Modules\ShopOwnerOperations\Services\ShopAccessService;
use App\Modules\ShopOwnerOperations\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The shop workspace home, the shop profile and its weekly opening hours. Routes
 * require work-in-shop (home) or manage-shop (profile, hours) for the shop in the
 * URL; changes go through MallShopDirectory's ShopService.
 */
class WorkspaceController extends Controller
{
    public function dashboard(Request $request, Shop $shop, ShopAccessService $access, StockService $stock): View
    {
        $areas = $access->areasFor($request->user(), $shop);

        return view('shop-owner.dashboard', [
            'shop' => $shop->load('mall', 'category'),
            'areas' => $areas,
            'lowStock' => in_array(ShopArea::Stock, $areas, true) ? $stock->lowStockCount($shop) : 0,
            'function' => $access->functionOf($request->user(), $shop),
            'application' => ShopApplication::query()->where('shop_id', $shop->getKey())->first(),
        ]);
    }

    public function editProfile(Shop $shop): View
    {
        return view('shop-owner.profile', ['shop' => $shop, 'hours' => $shop->openingHours->groupBy('weekday')]);
    }

    public function updateProfile(Request $request, Shop $shop, ShopService $shops): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $shops->updateProfile($shop, $data);

        return back()->with('status', __('The shop profile was saved.'));
    }

    public function updateHours(Request $request, Shop $shop, ShopService $shops): RedirectResponse
    {
        $request->validate([
            'hours' => ['array'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_next_day' => ['nullable', 'boolean'],
        ]);

        $intervals = [];

        foreach ((array) $request->input('hours', []) as $weekday => $row) {
            $opens = $row['opens_at'] ?? null;
            $closes = $row['closes_at'] ?? null;

            if (blank($opens) && blank($closes)) {
                continue;
            }

            if (blank($opens) || blank($closes) || ! in_array((int) $weekday, range(1, 7), true)) {
                return back()->withInput()->withErrors(['business' => __('Enter both an opening and a closing time, or leave the day empty for closed.')]);
            }

            $intervals[] = [
                'weekday' => (int) $weekday,
                'opens_at' => $opens,
                'closes_at' => $closes,
                'closes_next_day' => (bool) ($row['closes_next_day'] ?? false),
            ];
        }

        $shops->setOpeningHours($shop, $intervals);

        return back()->with('status', __('The opening hours were saved.'));
    }
}
