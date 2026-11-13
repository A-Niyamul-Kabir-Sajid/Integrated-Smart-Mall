<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\AccountLookupService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\PurchaseVerification\DTOs\IssuedCredentialData;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\PurchaseVerification\Services\VerificationCredentialService;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\ShopReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Sales rung up at the counter (Jira ISM-107/ISM-157): staff with the orders area record
 * a completed sale, optionally for a known customer account, in one transaction that also
 * takes the goods out of stock and issues the receipt. Staff may then issue the one-time
 * review code of a line, and void a sale entered by mistake, which puts the goods back.
 */
class SaleService
{
    public function __construct(
        private PurchaseService $purchases,
        private StockService $stock,
        private ReceiptService $receipts,
        private AccountLookupService $accounts,
        private VerificationCredentialService $credentials,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{items: list<array{shop_product_id: string, quantity: int|string, unit_price_amount?: string|float|null, line_discount_amount?: string|float|null}>, buyer_contact?: string|null, receipt_ref?: string|null}  $data
     */
    public function record(User $staff, Shop $shop, array $data): Purchase
    {
        Gate::forUser($staff)->authorize(ShopArea::Orders->ability(), $shop);

        $contact = trim((string) ($data['buyer_contact'] ?? ''));
        $buyer = null;

        if ($contact !== '') {
            $buyer = $this->accounts->activeAccountByContact($contact)
                ?? throw new BusinessRuleViolation('sale.unknown_buyer', __('No active customer account matches that email or mobile number. Leave it empty for a walk-in sale.'));
        }

        $reference = trim((string) ($data['receipt_ref'] ?? ''));

        return DB::transaction(function () use ($staff, $shop, $data, $buyer, $reference): Purchase {
            $purchase = $this->purchases->recordPurchase($staff, $shop, [
                'external_receipt_ref' => $reference === '' ? 'W-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)) : $reference,
                'buyer_user_id' => $buyer?->getKey(),
                'items' => $data['items'] ?? [],
            ]);

            foreach ($purchase->load('items.listing')->items as $item) {
                $this->stock->recordSale($item->listing, $item->quantity, 'purchase', $purchase->getKey(), $staff);
            }

            $this->receipts->issue($purchase, $staff);

            return $purchase;
        });
    }

    /**
     * Cancels a sale entered by mistake: the sale becomes voided, its review codes stop
     * working (through PurchaseVerification) and the goods go back into stock.
     */
    public function void(User $staff, Purchase $purchase, string $reason): Purchase
    {
        $shop = $purchase->shop;
        Gate::forUser($staff)->authorize(ShopArea::Orders->ability(), $shop);

        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new BusinessRuleViolation('sale.reason_required', __('Write a reason of 3 to 500 characters.'));
        }

        if (ShopReturn::query()->where('purchase_id', $purchase->getKey())->whereIn('status', ['requested', 'approved', 'completed'])->exists()) {
            throw new BusinessRuleViolation('sale.has_returns', __('This sale has returns. Finish or cancel them before voiding it.'));
        }

        return DB::transaction(function () use ($staff, $purchase, $reason, $shop): Purchase {
            $this->purchases->changeStatus($staff, $purchase, PurchaseStatus::Voided);

            foreach ($purchase->items()->with('listing')->get() as $item) {
                $this->stock->recordReturn($item->listing, $item->quantity, 'void', $purchase->getKey(), $staff);
            }

            $this->audit->record('sale.voided', $purchase, ['reason' => $reason], $shop->mall_id, $shop->getKey());

            return $purchase;
        });
    }

    /**
     * Issues the one-time review code of a sale line for a known customer. The raw secret is
     * in the result once and is never stored.
     */
    public function issueReviewCode(User $staff, PurchaseItem $item): IssuedCredentialData
    {
        $purchase = $item->purchase;
        Gate::forUser($staff)->authorize(ShopArea::Orders->ability(), $purchase->shop);

        if ($purchase->buyer_user_id === null) {
            throw new BusinessRuleViolation('sale.no_buyer', __('A review code needs a known customer account on the sale.'));
        }

        return $this->credentials->issueCredential($staff, $item);
    }
}
