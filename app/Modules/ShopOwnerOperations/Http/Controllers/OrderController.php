<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\OrderStatus;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shop's order inbox. Routes require the orders area for the shop in the URL, and an
 * order of another shop is not found through it; every change goes through OrderService.
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request, Shop $shop): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)]])['status'] ?? null;

        return view('shop-owner.orders.index', [
            'shop' => $shop,
            'status' => $status,
            'orders' => $this->orders->listForShop($request->user(), $shop, $status === null ? null : OrderStatus::from($status)),
            'counts' => $this->orders->countsByStatus($shop),
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function show(Shop $shop, ShopOrder $shopOrder): View
    {
        return view('shop-owner.orders.show', ['shop' => $shop, 'order' => $shopOrder->load(['items', 'customer:user_id,display_name', 'events.actor:user_id,display_name', 'purchase'])]);
    }

    public function accept(Request $request, Shop $shop, ShopOrder $shopOrder): RedirectResponse
    {
        $this->orders->accept($request->user(), $shopOrder->setRelation('shop', $shop), $request->input('note'));

        return back()->with('status', __('The order was accepted.'));
    }

    public function ready(Request $request, Shop $shop, ShopOrder $shopOrder): RedirectResponse
    {
        $this->orders->markReady($request->user(), $shopOrder->setRelation('shop', $shop), $request->input('note'));

        return back()->with('status', __('The customer was told the order is ready.'));
    }

    public function reject(Request $request, Shop $shop, ShopOrder $shopOrder): RedirectResponse
    {
        $this->orders->reject($request->user(), $shopOrder->setRelation('shop', $shop), (string) $request->input('reason'));

        return back()->with('status', __('The order was declined.'));
    }

    public function complete(Request $request, Shop $shop, ShopOrder $shopOrder): RedirectResponse
    {
        $data = $request->validate(['pickup_code' => ['required', 'string', 'max:12']]);

        $this->orders->complete($request->user(), $shopOrder->setRelation('shop', $shop), $data['pickup_code']);

        return back()->with('status', __('The goods were handed over and the sale was recorded.'));
    }
}
