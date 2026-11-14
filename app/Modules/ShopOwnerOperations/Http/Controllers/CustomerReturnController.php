<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ShopOwnerOperations\Enums\ReturnKind;
use App\Modules\ShopOwnerOperations\Models\ShopReturn;
use App\Modules\ShopOwnerOperations\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A customer asks to return or exchange goods of their own sale and follows the request.
 * Another customer's sale or return is not found.
 */
class CustomerReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function index(Request $request): View
    {
        return view('account.returns.index', ['returns' => $this->returns->listForCustomer($request->user())]);
    }

    public function show(ShopReturn $customerReturn): View
    {
        return view('account.returns.show', ['return' => $customerReturn->load(['items.purchaseItem', 'shop:shop_id,name', 'events'])]);
    }

    public function store(Request $request, Purchase $customerPurchase): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ReturnKind::class)],
            'reason' => ['required', 'string', 'max:500'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $return = $this->returns->request($request->user(), $customerPurchase, [
            'kind' => $data['kind'],
            'reason' => $data['reason'],
            'items' => $this->items($data['quantities']),
        ]);

        return redirect()->route('returns.show', $return)->with('status', __('Your request :number was sent to the shop.', ['number' => $return->return_number]));
    }

    public function cancel(Request $request, ShopReturn $customerReturn): RedirectResponse
    {
        $this->returns->cancel($request->user(), $customerReturn);

        return back()->with('status', __('Your request was cancelled.'));
    }

    /**
     * @param  array<string, mixed>  $quantities
     * @return list<array{purchase_item_id: string, quantity: int}>
     */
    private function items(array $quantities): array
    {
        return collect($quantities)->filter(fn ($quantity): bool => (int) $quantity > 0)
            ->map(fn ($quantity, $itemId): array => ['purchase_item_id' => (string) $itemId, 'quantity' => (int) $quantity])
            ->values()->all();
    }
}
