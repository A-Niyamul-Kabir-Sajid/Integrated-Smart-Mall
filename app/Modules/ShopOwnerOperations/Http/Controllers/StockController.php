<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ShopOwnerOperations\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The shop's stock screens. Routes require the stock area for the shop in the URL, and a
 * listing of another shop is not found through it; every change goes through StockService.
 */
class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request, Shop $shop): View
    {
        $lowOnly = $request->boolean('low');

        return view('shop-owner.stock.index', [
            'shop' => $shop,
            'lowOnly' => $lowOnly,
            'listings' => $this->stock->listForShop($request->user(), $shop, $lowOnly),
            'lowCount' => $this->stock->lowStockCount($shop),
            'mismatches' => $this->stock->reconcile($shop),
        ]);
    }

    public function show(Shop $shop, ShopProduct $shopListing): View
    {
        return view('shop-owner.stock.show', [
            'shop' => $shop,
            'listing' => $shopListing->load('variant.product'),
            'stock' => $this->stock->stockFor($shopListing),
            'movements' => $this->stock->movementsOf($shopListing),
        ]);
    }

    public function receive(Request $request, Shop $shop, ShopProduct $shopListing): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.StockService::MAX_QUANTITY],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $this->stock->receive($request->user(), $shopListing->setRelation('shop', $shop), (int) $data['quantity'], $data['reason'] ?? null);

        return back()->with('status', __('The delivery was added to stock.'));
    }

    public function adjust(Request $request, Shop $shop, ShopProduct $shopListing): RedirectResponse
    {
        $data = $request->validate([
            'counted' => ['required', 'integer', 'min:0', 'max:'.StockService::MAX_QUANTITY],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $this->stock->adjustTo($request->user(), $shopListing->setRelation('shop', $shop), (int) $data['counted'], $data['reason']);

        return back()->with('status', __('The stock count was saved.'));
    }

    public function threshold(Request $request, Shop $shop, ShopProduct $shopListing): RedirectResponse
    {
        $data = $request->validate(['threshold' => ['required', 'integer', 'min:0', 'max:'.StockService::MAX_QUANTITY]]);

        $this->stock->setThreshold($request->user(), $shopListing->setRelation('shop', $shop), (int) $data['threshold']);

        return back()->with('status', __('The low-stock alert level was saved.'));
    }
}
