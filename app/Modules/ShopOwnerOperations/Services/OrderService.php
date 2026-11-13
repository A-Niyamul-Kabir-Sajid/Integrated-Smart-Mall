<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\OrderKind;
use App\Modules\ShopOwnerOperations\Enums\OrderStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Models\ShopOrderEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Customer orders, reservations and click-and-collect (Jira ISM-107/ISM-108, decision D-014).
 *
 * A signed-in customer asks one shop to hold goods; staff with the orders area accept,
 * prepare and hand them over; handing over records the sale (PurchaseVerification), takes
 * the goods out of stock and issues the receipt in one transaction. The order keeps its own
 * price snapshot, so the sale is exactly what the customer agreed to. No payment is processed.
 */
class OrderService
{
    public const MAX_LINES = 20;

    public const MAX_LINE_QUANTITY = 20;

    public const MAX_OPEN_PER_CUSTOMER = 10;

    public const MIN_LEAD_MINUTES = 30;

    public const MAX_LEAD_DAYS = 7;

    public const EXPIRY_GRACE_HOURS = 24;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private DirectoryLookup $directory,
        private StockService $stock,
        private PurchaseService $purchases,
        private ReceiptService $receipts,
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * Places an order for the signed-in customer.
     *
     * @param  array{items: list<array{shop_product_id: string, quantity: int|string}>, kind: string, pickup_by: CarbonInterface|string, note?: string|null}  $data
     */
    public function place(User $customer, Shop $shop, array $data): ShopOrder
    {
        if (! $customer->isActive()) {
            throw new AuthorizationException;
        }

        if (! $this->directory->isShopOperational($shop->getKey())) {
            throw new BusinessRuleViolation('order.shop_not_operational', __('This shop is not taking orders right now.'));
        }

        $kind = OrderKind::tryFrom((string) ($data['kind'] ?? ''))
            ?? throw new BusinessRuleViolation('order.invalid_kind', __('Choose reserve or order ahead.'));

        $pickupBy = $this->pickupTime($data['pickup_by'] ?? null);

        $open = ShopOrder::query()->where('customer_user_id', $customer->getKey())->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::openCases()))->count();

        if ($open >= self::MAX_OPEN_PER_CUSTOMER) {
            throw new BusinessRuleViolation('order.too_many_open', __('You already have :max open orders. Collect or cancel some first.', ['max' => self::MAX_OPEN_PER_CUSTOMER]));
        }

        ['lines' => $lines, 'currency' => $currency, 'subtotal' => $subtotal] = $this->buildLines($shop, $data['items'] ?? []);

        $note = filled($data['note'] ?? null) ? mb_substr(trim($data['note']), 0, 500) : null;

        $order = DB::transaction(function () use ($customer, $shop, $kind, $pickupBy, $lines, $currency, $subtotal, $note): ShopOrder {
            $order = $this->createOrder([
                'shop_id' => $shop->getKey(),
                'customer_user_id' => $customer->getKey(),
                'kind' => $kind,
                'status' => OrderStatus::Requested,
                'currency' => $currency,
                'subtotal_amount' => $this->money($subtotal),
                'discount_amount' => '0.00',
                'total_amount' => $this->money($subtotal),
                'pickup_code' => $this->pickupCode(),
                'pickup_by' => $pickupBy,
                'customer_note' => $note,
            ]);

            $order->items()->createMany($lines);
            $this->recordEvent($order, null, OrderStatus::Requested, $customer, null);
            $this->audit->record('order.placed', $order, ['number' => $order->order_number, 'lines' => count($lines), 'total' => (string) $order->total_amount], $shop->mall_id, $shop->getKey());

            return $order;
        });

        foreach ($this->memberships->shopOwners($shop) as $owner) {
            $owner->notify(new SystemNotice(__('New order'), __(':number from :name is waiting for you.', ['number' => $order->order_number, 'name' => $customer->display_name]), route('workspace.orders.show', [$shop, $order])));
        }

        return $order->load('items');
    }

    public function accept(User $staff, ShopOrder $order, ?string $note = null): ShopOrder
    {
        $this->authorizeStaff($staff, $order);

        return $this->moveAndTell($order, OrderStatus::Accepted, $staff, $note, __('Your order was accepted'), __(':number is accepted. We will tell you when it is ready.', ['number' => $order->order_number]));
    }

    public function markReady(User $staff, ShopOrder $order, ?string $note = null): ShopOrder
    {
        $this->authorizeStaff($staff, $order);

        return $this->moveAndTell($order, OrderStatus::Ready, $staff, $note, __('Your order is ready'), __(':number is ready. Show your pickup code :code at :shop.', ['number' => $order->order_number, 'code' => $order->pickup_code, 'shop' => $order->shop->name]));
    }

    public function reject(User $staff, ShopOrder $order, string $reason): ShopOrder
    {
        $this->authorizeStaff($staff, $order);
        $reason = $this->requiredReason($reason);

        return $this->moveAndTell($order, OrderStatus::Rejected, $staff, $reason, __('Your order was declined'), __(':number was declined: :reason', ['number' => $order->order_number, 'reason' => $reason]), ['shop_note' => $reason]);
    }

    /**
     * The customer withdraws an order that has not been collected.
     */
    public function cancel(User $customer, ShopOrder $order): ShopOrder
    {
        if ($order->customer_user_id !== $customer->getKey()) {
            throw new AuthorizationException;
        }

        return $this->move($order, OrderStatus::Cancelled, $customer, null);
    }

    /**
     * Hands the goods over: checks the pickup code, records the sale at the order's prices,
     * takes the goods out of stock and issues the receipt, all or nothing.
     */
    public function complete(User $staff, ShopOrder $order, string $pickupCode): ShopOrder
    {
        $this->authorizeStaff($staff, $order);

        $completed = DB::transaction(function () use ($staff, $order, $pickupCode): ShopOrder {
            $locked = ShopOrder::query()->with(['items.listing', 'shop'])->lockForUpdate()->findOrFail($order->getKey());

            if (! in_array($locked->status, [OrderStatus::Accepted, OrderStatus::Ready], true)) {
                throw new BusinessRuleViolation('order.not_collectable', __('Only an accepted or ready order can be handed over.'));
            }

            if (! hash_equals($locked->pickup_code, Str::upper(trim($pickupCode)))) {
                throw new BusinessRuleViolation('order.wrong_pickup_code', __('The pickup code does not match this order.'));
            }

            $lineCents = $locked->items->map(fn ($item): int => $this->cents($item->line_total_amount))->all();
            $discounts = DiscountAllocator::allocate($this->cents($locked->discount_amount), $lineCents);

            $purchase = $this->purchases->recordPurchase($staff, $locked->shop, [
                'external_receipt_ref' => $locked->order_number,
                'buyer_user_id' => $locked->customer_user_id,
                'items' => $locked->items->values()->map(fn ($item, int $index): array => [
                    'shop_product_id' => $item->shop_product_id,
                    'quantity' => $item->quantity,
                    'unit_price_amount' => (string) $item->unit_price_amount,
                    'line_discount_amount' => $this->money($discounts[$index]),
                ])->all(),
            ]);

            foreach ($locked->items as $item) {
                $this->stock->recordSale($item->listing, $item->quantity, 'purchase', $purchase->getKey(), $staff);
            }

            $this->receipts->issue($purchase, $staff);

            $from = $locked->status;
            $locked->update(['status' => OrderStatus::Completed, 'purchase_id' => $purchase->getKey(), 'completed_at' => now()]);
            $this->recordEvent($locked, $from, OrderStatus::Completed, $staff, null);
            $this->audit->record('order.completed', $locked, ['number' => $locked->order_number, 'purchase' => $purchase->getKey()], $locked->shop->mall_id, $locked->shop_id);

            return $locked;
        });

        $order->setRawAttributes($completed->getAttributes(), true);
        $customer = $order->customer;
        $customer?->notify(new SystemNotice(__('Order collected'), __('Thank you! Your receipt for :number is in your account.', ['number' => $order->order_number]), route('orders.show', $order)));

        return $order;
    }

    /**
     * Closes open orders that were not collected within the grace period after their pickup time.
     */
    public function expireOverdue(): int
    {
        $expired = 0;

        ShopOrder::query()
            ->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::openCases()))
            ->where('pickup_by', '<', now()->subHours(self::EXPIRY_GRACE_HOURS))
            ->each(function (ShopOrder $order) use (&$expired): void {
                try {
                    $this->move($order, OrderStatus::Expired, null, __('Not collected in time'));
                    $expired++;
                } catch (BusinessRuleViolation) {
                    // Another request closed the order first.
                }
            });

        return $expired;
    }

    /**
     * The shop's orders, newest first, optionally of one status.
     *
     * @return LengthAwarePaginator<int, ShopOrder>
     */
    public function listForShop(User $staff, Shop $shop, ?OrderStatus $status = null, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($staff)->authorize(ShopArea::Orders->ability(), $shop);

        return ShopOrder::query()->where('shop_id', $shop->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->with('customer:user_id,display_name')
            ->withCount('items')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The customer's own orders, newest first.
     *
     * @return LengthAwarePaginator<int, ShopOrder>
     */
    public function listForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator
    {
        return ShopOrder::query()->where('customer_user_id', $customer->getKey())
            ->with('shop:shop_id,name')
            ->withCount('items')
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Counts of the shop's orders per status, for the workspace menu.
     *
     * @return Collection<string, int>
     */
    public function countsByStatus(Shop $shop): Collection
    {
        return ShopOrder::query()->where('shop_id', $shop->getKey())
            ->select('status', DB::raw('count(*) as total'))->groupBy('status')
            ->pluck('total', 'status')->map(fn ($total): int => (int) $total);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOrder(array $attributes): ShopOrder
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return ShopOrder::query()->create([...$attributes, 'order_number' => 'ORD-'.$this->randomCode(8)]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not allocate a unique order number.');
    }

    /**
     * Applies a status change under a row lock and writes the history entry.
     *
     * @param  array<string, mixed>  $extra
     */
    private function move(ShopOrder $order, OrderStatus $to, ?User $actor, ?string $note, array $extra = []): ShopOrder
    {
        return DB::transaction(function () use ($order, $to, $actor, $note, $extra): ShopOrder {
            $locked = ShopOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $from = $locked->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new BusinessRuleViolation('order.invalid_transition', __('A :from order cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
            }

            $locked->update([...$extra, 'status' => $to]);
            $this->recordEvent($locked, $from, $to, $actor, $note);
            $this->audit->record('order.'.$to->value, $locked, array_filter(['number' => $locked->order_number, 'note' => $note]), $locked->shop->mall_id, $locked->shop_id);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function moveAndTell(ShopOrder $order, OrderStatus $to, User $staff, ?string $note, string $title, string $body, array $extra = []): ShopOrder
    {
        $this->move($order, $to, $staff, $note, $extra);
        $order->customer->notify(new SystemNotice($title, $body, route('orders.show', $order)));

        return $order;
    }

    private function recordEvent(ShopOrder $order, ?OrderStatus $from, OrderStatus $to, ?User $actor, ?string $note): void
    {
        ShopOrderEvent::query()->create([
            'shop_order_id' => $order->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->getKey(),
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'created_at' => now(),
        ]);
    }

    private function authorizeStaff(User $staff, ShopOrder $order): void
    {
        Gate::forUser($staff)->authorize(ShopArea::Orders->ability(), $order->shop);
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new BusinessRuleViolation('order.reason_required', __('Write a reason of 3 to 500 characters.'));
        }

        return $reason;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array{lines: list<array<string, mixed>>, currency: string, subtotal: int}
     */
    private function buildLines(Shop $shop, array $items): array
    {
        $items = array_values(array_filter($items, fn ($item): bool => is_array($item)));

        if ($items === [] || count($items) > self::MAX_LINES) {
            throw new BusinessRuleViolation('order.invalid_lines', __('An order needs between 1 and :max products.', ['max' => self::MAX_LINES]));
        }

        $ids = array_map(fn (array $item): string => (string) ($item['shop_product_id'] ?? ''), $items);

        if (count(array_unique($ids)) !== count($ids)) {
            throw new BusinessRuleViolation('order.duplicate_listing', __('List each product once and raise its quantity instead.'));
        }

        $listings = ShopProduct::query()->with('variant.product')->whereIn('shop_product_id', array_filter($ids, Str::isUuid(...)))->get()->keyBy('shop_product_id');

        $lines = [];
        $subtotal = 0;
        $currencies = [];

        foreach ($items as $index => $item) {
            $listing = $listings->get($ids[$index]);

            if ($listing === null || $listing->shop_id !== $shop->getKey() || $listing->status !== 'active') {
                throw new BusinessRuleViolation('order.listing_unavailable', __('Line :line is not a product this shop sells.', ['line' => $index + 1]));
            }

            $quantity = $item['quantity'] ?? null;

            if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1 || (int) $quantity > self::MAX_LINE_QUANTITY) {
                throw new BusinessRuleViolation('order.invalid_quantity', __('Quantities run from 1 to :max.', ['max' => self::MAX_LINE_QUANTITY]));
            }

            $quantity = (int) $quantity;
            $label = $this->label($listing);

            if ($listing->availability_status === 'unavailable' || ! $this->stock->canSupply($listing, $quantity)) {
                throw new BusinessRuleViolation('order.out_of_stock', __(':label is not available in that quantity.', ['label' => $label]));
            }

            $unit = $this->cents($listing->price_amount);
            $lineCents = $unit * $quantity;
            $subtotal += $lineCents;
            $currencies[$listing->currency] = true;

            $lines[] = [
                'line_no' => $index + 1,
                'shop_product_id' => $listing->getKey(),
                'label_snapshot' => $label,
                'quantity' => $quantity,
                'unit_price_amount' => $this->money($unit),
                'line_total_amount' => $this->money($lineCents),
            ];
        }

        if (count($currencies) !== 1) {
            throw new BusinessRuleViolation('order.currency_mismatch', __('All products of an order must use the same currency.'));
        }

        return ['lines' => $lines, 'currency' => (string) array_key_first($currencies), 'subtotal' => $subtotal];
    }

    private function pickupTime(CarbonInterface|string|null $value): CarbonImmutable
    {
        try {
            $time = $value === null || $value === '' ? null : CarbonImmutable::parse($value);
        } catch (\Throwable) {
            $time = null;
        }

        if ($time === null) {
            throw new BusinessRuleViolation('order.invalid_pickup_time', __('Choose when you will collect the order.'));
        }

        if ($time->lessThan(now()->addMinutes(self::MIN_LEAD_MINUTES)) || $time->greaterThan(now()->addDays(self::MAX_LEAD_DAYS))) {
            throw new BusinessRuleViolation('order.invalid_pickup_time', __('Choose a pickup time between :min minutes and :days days from now.', ['min' => self::MIN_LEAD_MINUTES, 'days' => self::MAX_LEAD_DAYS]));
        }

        return $time;
    }

    private function label(ShopProduct $listing): string
    {
        $variant = $listing->variant;
        $label = $variant->label === '' ? $variant->product->name : "{$variant->product->name} - {$variant->label}";

        return Str::limit($label, 200, '');
    }

    private function pickupCode(): string
    {
        return $this->randomCode(6);
    }

    private function randomCode(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    private function cents(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
