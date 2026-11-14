<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\ShopOwnerOperations\Enums\ReturnKind;
use App\Modules\ShopOwnerOperations\Services\ReceiptService;
use App\Modules\ShopOwnerOperations\Services\ReturnService;
use App\Modules\ShopOwnerOperations\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Counter sales of one shop: record a sale, read it and its receipt, issue review codes,
 * void a mistake. Routes require the orders area for the shop in the URL; a sale of another
 * shop is not found through it.
 */
class SaleController extends Controller
{
    public function __construct(private SaleService $sales, private PurchaseService $purchases, private ReceiptService $receipts, private ReturnService $returns) {}

    public function index(Request $request, Shop $shop): View
    {
        return view('shop-owner.sales.index', ['shop' => $shop, 'purchases' => $this->purchases->listForShop($request->user(), $shop)]);
    }

    public function create(Shop $shop): View
    {
        return view('shop-owner.sales.create', [
            'shop' => $shop,
            'listings' => ShopProduct::query()->where('shop_id', $shop->getKey())->where('status', 'active')->with('variant.product')->orderBy('seller_sku')->limit(200)->get(),
        ]);
    }

    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'buyer_contact' => ['nullable', 'string', 'max:254'],
            'receipt_ref' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:'.PurchaseService::MAX_QUANTITY],
            'lines.*.unit_price_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($data['lines'])
            ->filter(fn ($line): bool => (int) ($line['quantity'] ?? 0) > 0)
            ->map(fn ($line, $listingId): array => [
                'shop_product_id' => (string) $listingId,
                'quantity' => (int) $line['quantity'],
                'unit_price_amount' => $line['unit_price_amount'] ?? null,
                'line_discount_amount' => $line['line_discount_amount'] ?? null,
            ])->values()->all();

        $purchase = $this->sales->record($request->user(), $shop, [
            'items' => $items,
            'buyer_contact' => $data['buyer_contact'] ?? null,
            'receipt_ref' => $data['receipt_ref'] ?? null,
        ]);

        return redirect()->route('workspace.sales.show', [$shop, $purchase])->with('status', __('The sale was recorded.'));
    }

    public function show(Request $request, Shop $shop, Purchase $shopPurchase): View
    {
        $purchase = $this->purchases->findForShop($request->user(), $shop, $shopPurchase->getKey());

        return view('shop-owner.sales.show', [
            'shop' => $shop,
            'purchase' => $purchase->load(['items.verificationToken', 'buyer:user_id,display_name']),
            'receipt' => $this->receipts->forPurchase($purchase),
            'returnForm' => [
                'blocked' => $this->returns->eligibility($purchase),
                'committed' => $this->returns->committedQuantities($purchase),
                'kinds' => ReturnKind::cases(),
                'action' => route('workspace.returns.store', [$shop, $purchase]),
            ],
        ]);
    }

    public function receipt(Request $request, Shop $shop, Purchase $shopPurchase): View
    {
        $purchase = $this->purchases->findForShop($request->user(), $shop, $shopPurchase->getKey());

        return view('receipts.show', ['purchase' => $purchase->load('shop.mall'), 'receipt' => $this->receipts->forPurchase($purchase), 'backUrl' => route('workspace.sales.show', [$shop, $purchase])]);
    }

    public function reviewCode(Request $request, Shop $shop, Purchase $shopPurchase, PurchaseItem $item): RedirectResponse
    {
        abort_unless($item->purchase_id === $shopPurchase->getKey(), 404);

        $issued = $this->sales->issueReviewCode($request->user(), $item);

        return back()->with('status', __('The review code was issued. Give it to the customer now; it cannot be shown again.'))->with('review_code', ['line' => $item->line_no, 'secret' => $issued->secret]);
    }

    public function void(Request $request, Shop $shop, Purchase $shopPurchase): RedirectResponse
    {
        $this->sales->void($request->user(), $shopPurchase, (string) $request->input('reason'));

        return back()->with('status', __('The sale was voided and the goods are back in stock.'));
    }
}
