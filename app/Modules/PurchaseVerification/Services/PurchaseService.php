<?php

namespace App\Modules\PurchaseVerification\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Records and reads the sales that later back purchase verification.
 *
 * A sale is trusted because authorised staff of the shop enter it (the
 * work-in-shop ability), never because an external system says so. The service
 * computes every amount itself, snapshots product labels and prices, and keeps
 * all lines and the header in one transaction.
 */
class PurchaseService
{
    public const MAX_LINES = 100;

    public const MAX_QUANTITY = 10000;

    private const AMOUNT_PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    public function __construct(
        private DirectoryLookup $directory,
        private AuditLogger $audit,
    ) {}

    /**
     * Records a completed sale for the shop on behalf of the signed-in staff member.
     *
     * Each line takes the listing's current price unless the staff member enters the
     * price actually charged. A supplied total must match the sum of the lines.
     *
     * @param  array{external_receipt_ref: string, items: list<array{shop_product_id: string, quantity: int|string, unit_price_amount?: string|float|null, line_discount_amount?: string|float|null}>, buyer_user_id?: string|null, purchased_at?: CarbonInterface|string|null, total_amount?: string|float|null}  $data
     */
    public function recordPurchase(User $recorder, Shop $shop, array $data): Purchase
    {
        Gate::forUser($recorder)->authorize(Ability::WorkInShop->value, $shop);

        if (! $this->directory->isShopOperational($shop->getKey())) {
            throw new BusinessRuleViolation('purchase.shop_not_operational', __('Only an active shop can record sales.'));
        }

        $receipt = trim((string) ($data['external_receipt_ref'] ?? ''));

        if ($receipt === '' || mb_strlen($receipt) > 120) {
            throw new BusinessRuleViolation('purchase.invalid_receipt', __('Enter a receipt reference of up to 120 characters.'));
        }

        $purchasedAt = $this->purchaseTime($data['purchased_at'] ?? null);
        $buyerId = $this->knownBuyerId($data['buyer_user_id'] ?? null);
        $this->ensureReceiptFree($shop, $receipt);

        ['lines' => $lines, 'currency' => $currency, 'total' => $totalCents] = $this->buildLines($shop, $data['items'] ?? []);

        if (isset($data['total_amount']) && $data['total_amount'] !== '' && $this->toCents($data['total_amount'], 'purchase.invalid_amount') !== $totalCents) {
            throw new BusinessRuleViolation('purchase.total_mismatch', __('The total does not match the sum of the lines (:total).', ['total' => $this->fromCents($totalCents)]));
        }

        try {
            $purchase = DB::transaction(function () use ($shop, $buyerId, $receipt, $currency, $totalCents, $purchasedAt, $lines): Purchase {
                $purchase = Purchase::query()->create([
                    'shop_id' => $shop->getKey(),
                    'buyer_user_id' => $buyerId,
                    'external_receipt_ref' => $receipt,
                    'status' => PurchaseStatus::Completed,
                    'currency' => $currency,
                    'total_amount' => $this->fromCents($totalCents),
                    'purchased_at' => $purchasedAt,
                    'recorded_at' => now(),
                ]);

                $purchase->items()->createMany($lines);

                return $purchase;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('purchase.duplicate_receipt', __('This receipt reference was already recorded for the shop.'));
        }

        $this->audit->record('purchase.recorded', $purchase, [
            'receipt' => $receipt,
            'total' => (string) $purchase->total_amount,
            'lines' => count($lines),
            'known_buyer' => $buyerId !== null,
        ], $shop->mall_id, $shop->getKey());

        return $purchase->load('items');
    }

    /**
     * Voids or refunds a recorded sale. Voided and refunded sales stop backing new
     * verification credentials; the record itself is kept.
     */
    public function changeStatus(User $actor, Purchase $purchase, PurchaseStatus $to): Purchase
    {
        $shop = $purchase->shop;
        Gate::forUser($actor)->authorize(Ability::WorkInShop->value, $shop);

        if (! in_array($to, $purchase->status->allowedTransitions(), true)) {
            throw new BusinessRuleViolation('purchase.invalid_transition', __('A :from purchase cannot become :to.', ['from' => $purchase->status->value, 'to' => $to->value]));
        }

        $from = $purchase->status;
        $purchase->update(['status' => $to]);

        $this->audit->record('purchase.status_changed', $purchase, ['from' => $from->value, 'to' => $to->value], $shop->mall_id, $shop->getKey());

        return $purchase;
    }

    /**
     * A shop's sales, newest first, for staff of that shop only.
     *
     * @return LengthAwarePaginator<int, Purchase>
     */
    public function listForShop(User $actor, Shop $shop, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize(Ability::WorkInShop->value, $shop);

        return Purchase::query()
            ->where('shop_id', $shop->getKey())
            ->withCount('items')
            ->orderByDesc('purchased_at')
            ->paginate($perPage);
    }

    /**
     * One sale of the shop, with its lines, for staff of that shop only.
     */
    public function findForShop(User $actor, Shop $shop, string $purchaseId): Purchase
    {
        Gate::forUser($actor)->authorize(Ability::WorkInShop->value, $shop);

        return Purchase::query()->with('items')->where('shop_id', $shop->getKey())->findOrFail($purchaseId);
    }

    /**
     * The customer's own sales, newest first. A walk-in sale has no buyer and is never listed here.
     *
     * @return LengthAwarePaginator<int, Purchase>
     */
    public function listForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator
    {
        return Purchase::query()
            ->where('buyer_user_id', $customer->getKey())
            ->with('shop:shop_id,name')
            ->withCount('items')
            ->orderByDesc('purchased_at')
            ->paginate($perPage);
    }

    /**
     * One of the customer's own sales, with its lines; anything else is not found.
     */
    public function findForCustomer(User $customer, string $purchaseId): Purchase
    {
        return Purchase::query()->with(['items', 'shop:shop_id,name'])->where('buyer_user_id', $customer->getKey())->findOrFail($purchaseId);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array{lines: list<array<string, mixed>>, currency: string, total: int}
     */
    private function buildLines(Shop $shop, array $items): array
    {
        if ($items === [] || count($items) > self::MAX_LINES) {
            throw new BusinessRuleViolation('purchase.invalid_lines', __('A sale needs between 1 and :max lines.', ['max' => self::MAX_LINES]));
        }

        $items = array_values($items);
        $listingIds = array_map(fn (array $item): string => (string) ($item['shop_product_id'] ?? ''), $items);

        if (count(array_unique($listingIds)) !== count($listingIds)) {
            throw new BusinessRuleViolation('purchase.duplicate_listing', __('List each product once and raise its quantity instead.'));
        }

        /** @var Collection<string, ShopProduct> $listings */
        $listings = ShopProduct::query()->with('variant.product')
            ->whereIn('shop_product_id', array_filter($listingIds, Str::isUuid(...)))
            ->get()->keyBy('shop_product_id');

        $lines = [];
        $total = 0;
        $currencies = [];

        foreach ($items as $index => $item) {
            $listing = $listings->get($listingIds[$index]);

            if ($listing === null) {
                throw new BusinessRuleViolation('purchase.unknown_listing', __('Line :line refers to a product that does not exist.', ['line' => $index + 1]));
            }

            if ($listing->shop_id !== $shop->getKey()) {
                throw new BusinessRuleViolation('purchase.listing_other_shop', __('Line :line is not a product of this shop.', ['line' => $index + 1]));
            }

            $quantity = $this->quantity($item['quantity'] ?? null, $index + 1);
            $charged = $item['unit_price_amount'] ?? null;
            $unitCents = $this->toCents($charged === null || $charged === '' ? $listing->price_amount : $charged, 'purchase.invalid_amount');
            $discount = $item['line_discount_amount'] ?? null;
            $discountCents = $this->toCents($discount === null || $discount === '' ? 0 : $discount, 'purchase.invalid_amount');
            $grossCents = $quantity * $unitCents;

            if ($discountCents > $grossCents) {
                throw new BusinessRuleViolation('purchase.discount_too_large', __('The discount on line :line is more than the line price.', ['line' => $index + 1]));
            }

            $lineCents = $grossCents - $discountCents;
            $total += $lineCents;
            $currencies[$listing->currency] = true;

            $lines[] = [
                'line_no' => $index + 1,
                'shop_product_id' => $listing->getKey(),
                'product_label_snapshot' => $this->label($listing),
                'quantity' => $quantity,
                'unit_price_amount' => $this->fromCents($unitCents),
                'line_discount_amount' => $this->fromCents($discountCents),
                'line_total_amount' => $this->fromCents($lineCents),
            ];
        }

        if (count($currencies) !== 1) {
            throw new BusinessRuleViolation('purchase.currency_mismatch', __('All lines of a sale must use the same currency.'));
        }

        return ['lines' => $lines, 'currency' => (string) array_key_first($currencies), 'total' => $total];
    }

    private function label(ShopProduct $listing): string
    {
        $variant = $listing->variant;
        $label = $variant->label === '' ? $variant->product->name : "{$variant->product->name} - {$variant->label}";

        return Str::limit($label, 200, '');
    }

    private function quantity(mixed $quantity, int $line): int
    {
        if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1 || (int) $quantity > self::MAX_QUANTITY) {
            throw new BusinessRuleViolation('purchase.invalid_quantity', __('The quantity on line :line must be a whole number from 1 to :max.', ['line' => $line, 'max' => self::MAX_QUANTITY]));
        }

        return (int) $quantity;
    }

    private function purchaseTime(CarbonInterface|string|null $value): CarbonImmutable
    {
        $time = $value === null || $value === '' ? CarbonImmutable::now() : CarbonImmutable::parse($value);

        if ($time->isAfter(CarbonImmutable::now()->addMinutes(5))) {
            throw new BusinessRuleViolation('purchase.future_date', __('A sale cannot be dated in the future.'));
        }

        return $time;
    }

    private function knownBuyerId(?string $buyerId): ?string
    {
        if ($buyerId === null || $buyerId === '') {
            return null;
        }

        $buyer = Str::isUuid($buyerId) ? User::query()->find($buyerId) : null;

        if ($buyer === null || ! $buyer->isActive()) {
            throw new BusinessRuleViolation('purchase.unknown_buyer', __('The buyer is not an active customer account.'));
        }

        return $buyer->getKey();
    }

    private function ensureReceiptFree(Shop $shop, string $receipt): void
    {
        if (Purchase::query()->where('shop_id', $shop->getKey())->where('external_receipt_ref', $receipt)->exists()) {
            throw new BusinessRuleViolation('purchase.duplicate_receipt', __('This receipt reference was already recorded for the shop.'));
        }
    }

    private function toCents(mixed $amount, string $rule): int
    {
        $text = is_float($amount) ? number_format($amount, 2, '.', '') : trim((string) $amount);

        if (preg_match(self::AMOUNT_PATTERN, $text) !== 1) {
            throw new BusinessRuleViolation($rule, __('Enter an amount of zero or more with at most two decimals.'));
        }

        return (int) round(((float) $text) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
