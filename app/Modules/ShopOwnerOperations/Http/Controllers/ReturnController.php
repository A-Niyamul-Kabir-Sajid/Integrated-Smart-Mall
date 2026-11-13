<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ShopOwnerOperations\Enums\ReturnKind;
use App\Modules\ShopOwnerOperations\Enums\ReturnStatus;
use App\Modules\ShopOwnerOperations\Models\ShopReturn;
use App\Modules\ShopOwnerOperations\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shop's returns. Routes require the orders area for the shop in the URL; a return or
 * sale of another shop is not found through it.
 */
class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function index(Request $request, Shop $shop): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(ReturnStatus::class)]])['status'] ?? null;

        return view('shop-owner.returns.index', [
            'shop' => $shop,
            'status' => $status,
            'returns' => $this->returns->listForShop($request->user(), $shop, $status === null ? null : ReturnStatus::from($status)),
            'statuses' => ReturnStatus::cases(),
        ]);
    }

    public function show(Shop $shop, ShopReturn $shopReturn): View
    {
        return view('shop-owner.returns.show', ['shop' => $shop, 'return' => $shopReturn->load(['items.purchaseItem', 'purchase', 'requestedBy:user_id,display_name', 'events.actor:user_id,display_name'])]);
    }

    public function store(Request $request, Shop $shop, Purchase $shopPurchase): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ReturnKind::class)],
            'reason' => ['required', 'string', 'max:500'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $return = $this->returns->recordAtCounter($request->user(), $shopPurchase->setRelation('shop', $shop), [
            'kind' => $data['kind'],
            'reason' => $data['reason'],
            'items' => collect($data['quantities'])->filter(fn ($quantity): bool => (int) $quantity > 0)
                ->map(fn ($quantity, $itemId): array => ['purchase_item_id' => (string) $itemId, 'quantity' => (int) $quantity])->values()->all(),
        ]);

        return redirect()->route('workspace.returns.show', [$shop, $return])->with('status', __('The return was recorded and approved.'));
    }

    public function approve(Request $request, Shop $shop, ShopReturn $shopReturn): RedirectResponse
    {
        $this->returns->approve($request->user(), $shopReturn->setRelation('shop', $shop), $request->input('note'));

        return back()->with('status', __('The return was approved.'));
    }

    public function reject(Request $request, Shop $shop, ShopReturn $shopReturn): RedirectResponse
    {
        $this->returns->reject($request->user(), $shopReturn->setRelation('shop', $shop), (string) $request->input('reason'));

        return back()->with('status', __('The return was declined.'));
    }

    public function complete(Request $request, Shop $shop, ShopReturn $shopReturn): RedirectResponse
    {
        $this->returns->complete($request->user(), $shopReturn->setRelation('shop', $shop), $request->boolean('restock'));

        return back()->with('status', __('The return is complete.'));
    }

    public function cancel(Request $request, Shop $shop, ShopReturn $shopReturn): RedirectResponse
    {
        $this->returns->cancel($request->user(), $shopReturn->setRelation('shop', $shop));

        return back()->with('status', __('The return was cancelled.'));
    }
}
