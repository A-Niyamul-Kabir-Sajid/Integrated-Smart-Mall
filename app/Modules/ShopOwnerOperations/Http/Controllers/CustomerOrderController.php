<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ShopOwnerOperations\Enums\OrderKind;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A signed-in customer orders from one listed shop and follows their own orders.
 * Order pages resolve only the signed-in customer's orders; anyone else's is not found.
 */
class CustomerOrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function create(Request $request, Mall $publicMall, Shop $publicShop): View
    {
        return view('account.orders.create', [
            'mall' => $publicMall,
            'shop' => $publicShop,
            'listings' => ShopProduct::query()->where('shop_id', $publicShop->getKey())->where('status', 'active')
                ->where('availability_status', '!=', 'unavailable')
                ->with('variant.product')->orderBy('seller_sku')->limit(100)->get(),
            'preselected' => (string) $request->query('listing'),
            'kinds' => OrderKind::cases(),
        ]);
    }

    public function store(Request $request, Mall $publicMall, Shop $publicShop): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:'.OrderService::MAX_LINE_QUANTITY],
            'kind' => ['required', Rule::enum(OrderKind::class)],
            'pickup_by' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $items = collect($data['quantities'])->filter(fn ($quantity): bool => (int) $quantity > 0)
            ->map(fn ($quantity, $listingId): array => ['shop_product_id' => (string) $listingId, 'quantity' => (int) $quantity])
            ->values()->all();

        $order = $this->orders->place($request->user(), $publicShop, [
            'items' => $items,
            'kind' => $data['kind'],
            'pickup_by' => $data['pickup_by'],
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('orders.show', $order)->with('status', __('Your order :number was sent to :shop.', ['number' => $order->order_number, 'shop' => $publicShop->name]));
    }

    public function index(Request $request): View
    {
        return view('account.orders.index', ['orders' => $this->orders->listForCustomer($request->user())]);
    }

    public function show(ShopOrder $customerOrder): View
    {
        return view('account.orders.show', ['order' => $customerOrder->load(['items', 'shop:shop_id,name,mall_id', 'events', 'purchase'])]);
    }

    public function cancel(Request $request, ShopOrder $customerOrder): RedirectResponse
    {
        $this->orders->cancel($request->user(), $customerOrder);

        return back()->with('status', __('Your order was cancelled.'));
    }
}
