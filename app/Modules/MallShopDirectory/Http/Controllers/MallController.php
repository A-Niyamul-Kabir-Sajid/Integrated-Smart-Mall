<?php

namespace App\Modules\MallShopDirectory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Services\DirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MallController extends Controller
{
    /**
     * Mall picker; a single active mall opens directly.
     */
    public function index(): View|RedirectResponse
    {
        $malls = Mall::query()->active()->orderBy('name')->get();

        if ($malls->count() === 1) {
            return redirect()->route('malls.show', ['publicMall' => $malls->first()->slug]);
        }

        return view('customer.malls.index', ['malls' => $malls]);
    }

    public function show(Mall $publicMall, DirectoryService $directory): View
    {
        $listed = fn ($shops) => $shops->whereBelongsTo($publicMall)->listed();

        return view('customer.malls.show', [
            'mall' => $publicMall,
            'floors' => $publicMall->floors()->active()->get(),
            'categories' => ShopCategory::query()->whereHas('shops', $listed)->withCount(['shops' => $listed])->orderBy('name')->get(),
            'shopCount' => Shop::query()->whereBelongsTo($publicMall)->listed()->count(),
            'openShopCount' => count($directory->openShopIds($publicMall)),
        ]);
    }
}
