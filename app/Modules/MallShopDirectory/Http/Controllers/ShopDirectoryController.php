<?php

namespace App\Modules\MallShopDirectory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Services\DirectoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopDirectoryController extends Controller
{
    public function index(Request $request, Mall $publicMall, DirectoryService $directory): View
    {
        $filters = $request->validate([
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
}
