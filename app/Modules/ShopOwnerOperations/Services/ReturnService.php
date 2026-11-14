<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\ReturnKind;
use App\Modules\ShopOwnerOperations\Enums\ReturnStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\ShopReturn;
use App\Modules\ShopOwnerOperations\Models\ShopReturnEvent;
use App\Modules\ShopOwnerOperations\Models\ShopReturnItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Returns and exchanges of recorded sales (Jira ISM-107/ISM-108, decision D-014).
 *
 * The buyer asks within the return window; staff with the orders area approve or decline,
 * then complete when the goods are back, optionally putting them into stock. The value is a
 * recorded amount the shop hands back at the counter: no payment is processed (C-02). When
 * every line of a sale has been returned for a refund, the sale becomes refunded and its
 * review codes stop working through PurchaseVerification.
 */
class ReturnService
{
    public const WINDOW_DAYS = 14;

    public const MAX_OPEN_PER_PURCHASE = 3;

    public function __construct(
        private PurchaseService $purchases,
        private StockService $stock,
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * The buyer of the sale asks to return or exchange goods.
     *
     * @param  array{items: list<array{purchase_item_id: string, quantity: int|string}>, kind: string, reason: string}  $data
     */
    public function request(User $customer, Purchase $purchase, array $data): ShopReturn
    {
        if ($purchase->buyer_user_id === null || $purchase->buyer_user_id !== $customer->getKey()) {
            throw new AuthorizationException;
        }

        $purchase->loadMissing('shop');
        $return = $this->create($customer, $purchase, $data);

        foreach ($this->memberships->shopOwners($purchase->shop) as $owner) {
            $owner->notify(new SystemNotice(__('New return request'), __(':number is waiting for a decision.', ['number' => $return->return_number]), route('workspace.returns.show', [$purchase->shop_id, $return])));
        }

        return $return;
    }

    /**
     * Staff record a return at the counter (including for walk-in sales); it is approved at once.
     *
     * @param  array{items: list<array{purchase_item_id: string, quantity: int|string}>, kind: string, reason: string}  $data
     */
    public function recordAtCounter(User $staff, Purchase $purchase, array $data): ShopReturn
    {
        $this->authorizeStaff($staff, $purchase->loadMissing('shop')->shop);

        return DB::transaction(function () use ($staff, $purchase, $data): ShopReturn {
            $return = $this->create($staff, $purchase, $data);

            return $this->move($return, ReturnStatus::Approved, $staff, __('Recorded at the counter'));
        });
    }

    public function approve(User $staff, ShopReturn $return, ?string $note = null): ShopReturn
    {
        $this->authorizeStaff($staff, $return->loadMissing('shop')->shop);
        $this->move($return, ReturnStatus::Approved, $staff, $note, ['decision_note' => $note, 'decided_at' => now()]);
        $this->tell($return, __('Your return was approved'), __(':number is approved. Bring the goods to the shop.', ['number' => $return->return_number]));

        return $return;
    }

    public function reject(User $staff, ShopReturn $return, string $reason): ShopReturn
    {
        $this->authorizeStaff($staff, $return->loadMissing('shop')->shop);

        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new BusinessRuleViolation('return.reason_required', __('Write a reason of 3 to 500 characters.'));
        }

        $this->move($return, ReturnStatus::Rejected, $staff, $reason, ['decision_note' => $reason, 'decided_at' => now()]);
        $this->tell($return, __('Your return was declined'), __(':number was declined: :reason', ['number' => $return->return_number, 'reason' => $reason]));

        return $return;
    }

    /**
     * The goods are back. With $restock they go into stock; a full refund-return of every
     * line turns the sale into a refunded sale.
     */
    public function complete(User $staff, ShopReturn $return, bool $restock): ShopReturn
    {
        $this->authorizeStaff($staff, $return->loadMissing('shop')->shop);

        DB::transaction(function () use ($staff, $return, $restock): void {
            $locked = ShopReturn::query()->with(['items.purchaseItem.listing', 'purchase.shop'])->lockForUpdate()->findOrFail($return->getKey());

            $this->move($locked, ReturnStatus::Completed, $staff, null, ['restocked' => $restock, 'completed_at' => now()]);

            if ($restock) {
                foreach ($locked->items as $item) {
                    $this->stock->recordReturn($item->purchaseItem->listing, $item->quantity, 'return', $locked->getKey(), $staff);
                }
            }

            if ($locked->kind === ReturnKind::Return && $this->isFullyReturned($locked->purchase)) {
                $this->purchases->changeStatus($staff, $locked->purchase, PurchaseStatus::Refunded);
            }

            $return->setRawAttributes($locked->getAttributes(), true);
        });

        $this->tell($return, __('Your return is complete'), __(':number is complete. Value: ৳:value', ['number' => $return->return_number, 'value' => (string) $return->value_amount]));

        return $return;
    }

    /**
     * The requester (or staff) withdraws a request that has not been completed.
     */
    public function cancel(User $actor, ShopReturn $return): ShopReturn
    {
        if ($return->requested_by_user_id !== $actor->getKey()) {
            $this->authorizeStaff($actor, $return->loadMissing('shop')->shop);
        }

        return $this->move($return, ReturnStatus::Cancelled, $actor, null);
    }

    /**
     * Quantity of each sale line already claimed by returns that are open or completed.
     *
     * @return Collection<string, int>
     */
    public function committedQuantities(Purchase $purchase): Collection
    {
        return ShopReturnItem::query()
            ->join('shop_returns', 'shop_returns.shop_return_id', '=', 'shop_return_items.shop_return_id')
            ->where('shop_returns.purchase_id', $purchase->getKey())
            ->whereIn('shop_returns.status', [ReturnStatus::Requested->value, ReturnStatus::Approved->value, ReturnStatus::Completed->value])
            ->select('shop_return_items.purchase_item_id', DB::raw('sum(shop_return_items.quantity) as total'))
            ->groupBy('shop_return_items.purchase_item_id')
            ->pluck('total', 'purchase_item_id')
            ->map(fn ($total): int => (int) $total);
    }

    /**
     * Whether a return of this sale may still be requested, and why not.
     */
    public function eligibility(Purchase $purchase): ?string
    {
        if ($purchase->status !== PurchaseStatus::Completed) {
            return __('Only a completed sale can be returned.');
        }

        if ($purchase->purchased_at->lt(now()->subDays(self::WINDOW_DAYS))) {
            return __('The :days-day return window for this sale has closed.', ['days' => self::WINDOW_DAYS]);
        }

        return null;
    }

    /**
     * @return LengthAwarePaginator<int, ShopReturn>
     */
    public function listForShop(User $staff, Shop $shop, ?ReturnStatus $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $this->authorizeStaff($staff, $shop);

        return ShopReturn::query()->where('shop_id', $shop->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->with('purchase:purchase_id,external_receipt_ref')
            ->withCount('items')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The returns of the customer's own sales.
     *
     * @return LengthAwarePaginator<int, ShopReturn>
     */
    public function listForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator
    {
        return ShopReturn::query()
            ->whereIn('purchase_id', Purchase::query()->where('buyer_user_id', $customer->getKey())->select('purchase_id'))
            ->with('shop:shop_id,name')
            ->withCount('items')
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array{items: list<array{purchase_item_id: string, quantity: int|string}>, kind: string, reason: string}  $data
     */
    private function create(User $requester, Purchase $purchase, array $data): ShopReturn
    {
        $kind = ReturnKind::tryFrom((string) ($data['kind'] ?? ''))
            ?? throw new BusinessRuleViolation('return.invalid_kind', __('Choose return or exchange.'));

        if (($why = $this->eligibility($purchase)) !== null) {
            throw new BusinessRuleViolation('return.not_eligible', $why);
        }

        $reason = trim((string) ($data['reason'] ?? ''));

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new BusinessRuleViolation('return.reason_required', __('Write a reason of 5 to 500 characters.'));
        }

        $open = ShopReturn::query()->where('purchase_id', $purchase->getKey())->whereIn('status', [ReturnStatus::Requested->value, ReturnStatus::Approved->value])->count();

        if ($open >= self::MAX_OPEN_PER_PURCHASE) {
            throw new BusinessRuleViolation('return.too_many_open', __('This sale already has :max open return requests.', ['max' => self::MAX_OPEN_PER_PURCHASE]));
        }

        ['lines' => $lines, 'value' => $value] = $this->buildLines($purchase, $data['items'] ?? []);

        $return = DB::transaction(function () use ($requester, $purchase, $kind, $reason, $lines, $value): ShopReturn {
            $return = $this->createReturn([
                'shop_id' => $purchase->shop_id,
                'purchase_id' => $purchase->getKey(),
                'requested_by_user_id' => $requester->getKey(),
                'kind' => $kind,
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'value_amount' => number_format($value / 100, 2, '.', ''),
            ]);

            $return->items()->createMany($lines);
            $this->recordEvent($return, null, ReturnStatus::Requested, $requester, null);
            $this->audit->record('return.requested', $return, ['number' => $return->return_number, 'kind' => $kind->value, 'value' => (string) $return->value_amount], $purchase->shop->mall_id, $purchase->shop_id);

            return $return;
        });

        return $return->load('items');
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array{lines: list<array<string, mixed>>, value: int}
     */
    private function buildLines(Purchase $purchase, array $items): array
    {
        $items = array_values(array_filter($items, fn ($item): bool => is_array($item)));

        if ($items === []) {
            throw new BusinessRuleViolation('return.no_lines', __('Choose at least one item to return.'));
        }

        $ids = array_map(fn (array $item): string => (string) ($item['purchase_item_id'] ?? ''), $items);

        if (count(array_unique($ids)) !== count($ids)) {
            throw new BusinessRuleViolation('return.duplicate_line', __('List each sale line once.'));
        }

        $saleLines = $purchase->items()->whereIn('purchase_item_id', array_filter($ids, Str::isUuid(...)))->get()->keyBy('purchase_item_id');
        $committed = $this->committedQuantities($purchase);

        $lines = [];
        $value = 0;

        foreach ($items as $index => $item) {
            $line = $saleLines->get($ids[$index]);

            if ($line === null) {
                throw new BusinessRuleViolation('return.unknown_line', __('Line :line is not part of this sale.', ['line' => $index + 1]));
            }

            $quantity = $item['quantity'] ?? null;

            if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1) {
                throw new BusinessRuleViolation('return.invalid_quantity', __('Quantities must be whole numbers of 1 or more.'));
            }

            $quantity = (int) $quantity;
            $remaining = $line->quantity - $committed->get($line->getKey(), 0);

            if ($quantity > $remaining) {
                throw new BusinessRuleViolation('return.too_many', __(':label: only :count can still be returned.', ['label' => $line->product_label_snapshot, 'count' => max(0, $remaining)]));
            }

            $lineValue = (int) round(((float) $line->line_total_amount) * 100 * $quantity / $line->quantity);
            $value += $lineValue;

            $lines[] = ['purchase_item_id' => $line->getKey(), 'quantity' => $quantity, 'value_amount' => number_format($lineValue / 100, 2, '.', '')];
        }

        return ['lines' => $lines, 'value' => $value];
    }

    private function isFullyReturned(Purchase $purchase): bool
    {
        $returned = ShopReturnItem::query()
            ->join('shop_returns', 'shop_returns.shop_return_id', '=', 'shop_return_items.shop_return_id')
            ->where('shop_returns.purchase_id', $purchase->getKey())
            ->where('shop_returns.kind', ReturnKind::Return->value)
            ->where('shop_returns.status', ReturnStatus::Completed->value)
            ->select('shop_return_items.purchase_item_id', DB::raw('sum(shop_return_items.quantity) as total'))
            ->groupBy('shop_return_items.purchase_item_id')
            ->pluck('total', 'purchase_item_id');

        return $purchase->items()->get()->every(fn ($line): bool => (int) ($returned[$line->getKey()] ?? 0) >= $line->quantity);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createReturn(array $attributes): ShopReturn
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                // A savepoint, so a clash on the number does not abort an enclosing PostgreSQL transaction.
                return DB::transaction(fn (): ShopReturn => ShopReturn::query()->create([...$attributes, 'return_number' => 'RET-'.Str::upper(Str::random(8))]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not allocate a unique return number.');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function move(ShopReturn $return, ReturnStatus $to, ?User $actor, ?string $note, array $extra = []): ShopReturn
    {
        return DB::transaction(function () use ($return, $to, $actor, $note, $extra): ShopReturn {
            $locked = ShopReturn::query()->with('shop')->lockForUpdate()->findOrFail($return->getKey());
            $from = $locked->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new BusinessRuleViolation('return.invalid_transition', __('A :from return cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
            }

            $locked->update([...$extra, 'status' => $to]);
            $this->recordEvent($locked, $from, $to, $actor, $note);
            $this->audit->record('return.'.$to->value, $locked, array_filter(['number' => $locked->return_number, 'note' => $note]), $locked->shop->mall_id, $locked->shop_id);

            $return->setRawAttributes($locked->getAttributes(), true);

            return $return;
        });
    }

    private function recordEvent(ShopReturn $return, ?ReturnStatus $from, ReturnStatus $to, ?User $actor, ?string $note): void
    {
        ShopReturnEvent::query()->create([
            'shop_return_id' => $return->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->getKey(),
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'created_at' => now(),
        ]);
    }

    private function authorizeStaff(User $staff, Shop $shop): void
    {
        Gate::forUser($staff)->authorize(ShopArea::Orders->ability(), $shop);
    }

    private function tell(ShopReturn $return, string $title, string $body): void
    {
        $buyer = $return->purchase()->with('buyer')->first()?->buyer;

        $buyer?->notify(new SystemNotice($title, $body, route('returns.show', $return)));
    }
}
