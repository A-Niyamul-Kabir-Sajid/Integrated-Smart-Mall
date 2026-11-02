<?php

namespace App\Modules\MallShopDirectory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Services\DirectoryService;
use App\Modules\MallShopDirectory\Services\OpeningStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopDirectoryController extends Controller
{
    public function index(Request $request, Mall $publicMall, DirectoryService $directory): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(DirectoryService::SORTS)],
            'category' => ['nullable', 'string', 'max:80'],
            'floor' => ['nullable', 'string', 'max:20'],
            'open_now' => ['nullable', 'boolean'],
        ]);

        return view('customer.shops.index', [
            'mall' => $publicMall,
            'shops' => $directory->browseShops($publicMall, [...$filters, 'open_now' => $request->boolean('open_now')]),
            'filters' => $filters,
            'floors' => $publicMall->floors()->active()->get(),
            'categories' => ShopCategory::query()->whereHas('shops', fn ($shops) => $shops->whereBelongsTo($publicMall)->listed())->orderBy('name')->get(),
        ]);
    }

    public function show(Mall $publicMall, Shop $publicShop, OpeningStatusService $openingStatus): View
    {
        $publicShop->load(['category', 'units.floor', 'openingHours']);
        $now = Date::now();

        return view('customer.shops.show', [
            'mall' => $publicMall,
            'shop' => $publicShop,
            'isOpenNow' => $publicShop->status === ShopStatus::Active && $openingStatus->isOpenAt($publicShop->openingHours, $now, $publicMall->timezone),
            'weeklySchedule' => $openingStatus->weeklySchedule($publicShop->openingHours),
            'todayWeekday' => $now->setTimezone($publicMall->timezone)->isoWeekday(),
        ]);
    }
}
