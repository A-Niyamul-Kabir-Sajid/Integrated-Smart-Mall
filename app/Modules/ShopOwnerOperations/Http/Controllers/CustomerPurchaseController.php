<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\ShopOwnerOperations\Enums\ReturnKind;
use App\Modules\ShopOwnerOperations\Services\ReceiptService;
use App\Modules\ShopOwnerOperations\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in customer's own recorded purchases and their receipts. Another customer's
 * purchase, or a walk-in sale without a buyer, is not found.
 */
class CustomerPurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases, private ReceiptService $receipts, private ReturnService $returns) {}

    public function index(Request $request): View
    {
        return view('account.purchases.index', ['purchases' => $this->purchases->listForCustomer($request->user())]);
    }

    public function show(Request $request, Purchase $customerPurchase): View
    {
        $purchase = $this->purchases->findForCustomer($request->user(), $customerPurchase->getKey());

        return view('receipts.show', [
            'purchase' => $purchase->load('shop.mall'),
            'receipt' => $this->receipts->forPurchase($purchase),
            'backUrl' => route('account.purchases.index'),
            'returnForm' => [
                'blocked' => $this->returns->eligibility($purchase),
                'committed' => $this->returns->committedQuantities($purchase),
                'kinds' => ReturnKind::cases(),
                'action' => route('returns.store', $purchase),
            ],
        ]);
    }
}
