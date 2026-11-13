<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ProductsCatalogue\Services\CatalogueService;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Enums\StockMovementType;
use App\Modules\ShopOwnerOperations\Models\ListingStock;
use App\Modules\ShopOwnerOperations\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The shop's stock (Jira ISM-104, decision D-014): a quantity per listing and an
 * append-only ledger. Every change runs in one transaction that locks the stock row,
 * writes the ledger entry with the resulting balance and keeps the listing's
 * availability flag in step. Staff with the stock area (catalogue editors, managers,
 * owners) receive and count stock; the sales and returns workflows call recordSale()
 * and recordReturn() after authorising the person themselves.
 */
class StockService
{
    public const MAX_QUANTITY = 1000000;

    public function __construct(
        private CatalogueService $catalogue,
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * Adds delivered goods to the listing's stock.
     */
    public function receive(User $actor, ShopProduct $listing, int $quantity, ?string $reason = null): ListingStock
    {
        $this->authorize($actor, $listing);
        $this->ensureQuantity($quantity);

        return $this->move($listing, StockMovementType::Receipt, $quantity, $this->cleanReason($reason), $actor);
    }

    /**
     * Sets the counted quantity (stocktake, damage, loss). A reason is required because
     * the change is not a delivery or a sale.
     */
    public function adjustTo(User $actor, ShopProduct $listing, int $counted, string $reason): ListingStock
    {
        $this->authorize($actor, $listing);

        if ($counted < 0 || $counted > self::MAX_QUANTITY) {
            throw new BusinessRuleViolation('stock.invalid_quantity', __('Enter a count from 0 to :max.', ['max' => self::MAX_QUANTITY]));
        }

        $reason = $this->cleanReason($reason);

        if ($reason === null) {
            throw new BusinessRuleViolation('stock.reason_required', __('Write a reason for the adjustment.'));
        }

        return DB::transaction(function () use ($actor, $listing, $counted, $reason): ListingStock {
            $stock = $this->lockedStock($listing);
            $delta = $counted - $stock->on_hand;

            if ($delta === 0) {
                throw new BusinessRuleViolation('stock.no_change', __('The count equals the quantity already on hand.'));
            }

            return $this->apply($stock, $listing, StockMovementType::Adjustment, $delta, $reason, $actor);
        });
    }

    public function setThreshold(User $actor, ShopProduct $listing, int $threshold): ListingStock
    {
        $this->authorize($actor, $listing);

        if ($threshold < 0 || $threshold > self::MAX_QUANTITY) {
            throw new BusinessRuleViolation('stock.invalid_threshold', __('Enter a threshold from 0 to :max. Zero switches the alert off.', ['max' => self::MAX_QUANTITY]));
        }

        return DB::transaction(function () use ($listing, $threshold): ListingStock {
            $stock = $this->lockedStock($listing);
            $stock->update(['low_stock_threshold' => $threshold, 'updated_at' => now()]);

            $this->audit->record('stock.threshold_changed', $listing, ['threshold' => $threshold], $listing->shop->mall_id, $listing->shop_id);

            return $stock;
        });
    }

    /**
     * Takes goods out of stock for a completed sale. The caller has authorised the person.
     * A tracked listing cannot go below zero; an untracked listing is left alone (null).
     */
    public function recordSale(ShopProduct $listing, int $quantity, string $referenceType, string $referenceId, ?User $actor = null): ?ListingStock
    {
        $this->ensureQuantity($quantity);

        return DB::transaction(function () use ($listing, $quantity, $referenceType, $referenceId, $actor): ?ListingStock {
            $stock = $this->lockedStock($listing, createIfMissing: false);

            if ($stock === null) {
                return null;
            }

            if ($stock->on_hand < $quantity) {
                throw new BusinessRuleViolation('stock.insufficient', __(':label has only :count in stock.', ['label' => $this->label($listing), 'count' => $stock->on_hand]));
            }

            return $this->apply($stock, $listing, StockMovementType::Sale, -$quantity, null, $actor, $referenceType, $referenceId);
        });
    }

    /**
     * Puts returned goods back on the shelf. Untracked listings are left alone (null).
     */
    public function recordReturn(ShopProduct $listing, int $quantity, string $referenceType, string $referenceId, ?User $actor = null): ?ListingStock
    {
        $this->ensureQuantity($quantity);

        return DB::transaction(function () use ($listing, $quantity, $referenceType, $referenceId, $actor): ?ListingStock {
            $stock = $this->lockedStock($listing, createIfMissing: false);

            return $stock === null ? null : $this->apply($stock, $listing, StockMovementType::Return, $quantity, null, $actor, $referenceType, $referenceId);
        });
    }

    /**
     * Whether the listing can supply the quantity: always true for untracked listings.
     */
    public function canSupply(ShopProduct $listing, int $quantity): bool
    {
        $stock = ListingStock::query()->find($listing->getKey());

        return $stock === null || $stock->on_hand >= $quantity;
    }

    /**
     * The shop's active listings with their stock rows (null when untracked).
     *
     * @return LengthAwarePaginator<int, ShopProduct>
     */
    public function listForShop(User $actor, Shop $shop, bool $lowOnly = false, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize(ShopArea::Stock->ability(), $shop);

        $query = ShopProduct::query()
            ->where('shop_id', $shop->getKey())->where('status', 'active')
            ->with('variant.product')
            ->orderBy('seller_sku');

        if ($lowOnly) {
            $query->whereIn('shop_product_id', $this->lowStockQuery($shop)->select('shop_product_id'));
        }

        $page = $query->paginate($perPage)->withQueryString();
        $stocks = ListingStock::query()->whereIn('shop_product_id', $page->getCollection()->modelKeys())->get()->keyBy('shop_product_id');
        $page->getCollection()->each(fn (ShopProduct $listing) => $listing->setRelation('stock', $stocks->get($listing->getKey())));

        return $page;
    }

    /**
     * Number of tracked listings at or below their threshold, for the workspace home.
     */
    public function lowStockCount(Shop $shop): int
    {
        return $this->lowStockQuery($shop)->count();
    }

    public function stockFor(ShopProduct $listing): ?ListingStock
    {
        return ListingStock::query()->find($listing->getKey());
    }

    /**
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function movementsOf(ShopProduct $listing, int $perPage = 20): LengthAwarePaginator
    {
        return StockMovement::query()->where('shop_product_id', $listing->getKey())
            ->with('actor:user_id,display_name')
            ->orderByDesc('stock_movement_id')
            ->paginate($perPage);
    }

    /**
     * Compares every tracked listing's quantity with the sum of its ledger and returns the
     * listings that disagree. A healthy shop returns an empty collection.
     *
     * @return Collection<int, array{shop_product_id: string, on_hand: int, ledger_total: int}>
     */
    public function reconcile(Shop $shop): Collection
    {
        $totals = StockMovement::query()->where('shop_id', $shop->getKey())
            ->select('shop_product_id', DB::raw('sum(quantity_delta) as ledger_total'))
            ->groupBy('shop_product_id')->pluck('ledger_total', 'shop_product_id');

        return ListingStock::query()->where('shop_id', $shop->getKey())->get()
            ->map(fn (ListingStock $stock): array => [
                'shop_product_id' => $stock->shop_product_id,
                'on_hand' => $stock->on_hand,
                'ledger_total' => (int) ($totals[$stock->shop_product_id] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['on_hand'] !== $row['ledger_total'])
            ->values();
    }

    private function move(ShopProduct $listing, StockMovementType $type, int $quantity, ?string $reason, ?User $actor): ListingStock
    {
        return DB::transaction(fn (): ListingStock => $this->apply($this->lockedStock($listing), $listing, $type, $quantity, $reason, $actor));
    }

    /**
     * Writes the ledger entry and the new quantity; the stock row is already locked.
     */
    private function apply(ListingStock $stock, ShopProduct $listing, StockMovementType $type, int $delta, ?string $reason, ?User $actor, ?string $referenceType = null, ?string $referenceId = null): ListingStock
    {
        $listing->loadMissing('shop');

        $before = $stock->on_hand;
        $after = $before + $delta;

        if ($after < 0 || $after > self::MAX_QUANTITY) {
            throw new BusinessRuleViolation('stock.out_of_range', __('The quantity on hand must stay between 0 and :max.', ['max' => self::MAX_QUANTITY]));
        }

        StockMovement::query()->create([
            'shop_product_id' => $listing->getKey(),
            'shop_id' => $listing->shop_id,
            'movement_type' => $type,
            'quantity_delta' => $delta,
            'balance_after' => $after,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'actor_user_id' => $actor?->getKey(),
            'created_at' => now(),
        ]);

        $stock->update(['on_hand' => $after, 'updated_at' => now()]);

        $this->audit->record('stock.'.$type->value, $listing, ['delta' => $delta, 'balance' => $after], $listing->shop->mall_id, $listing->shop_id);

        $this->catalogue->setAvailability($listing, $after === 0 ? 'unavailable' : 'available');

        $threshold = $stock->low_stock_threshold;

        if ($threshold > 0 && $before > $threshold && $after <= $threshold) {
            $this->alertLowStock($listing, $after, $threshold);
        }

        return $stock;
    }

    /**
     * The stock row of the listing, locked for update inside the caller's transaction.
     * A first receipt or count starts tracking; sales and returns on untracked listings do not.
     */
    private function lockedStock(ShopProduct $listing, bool $createIfMissing = true): ?ListingStock
    {
        if ($createIfMissing) {
            ListingStock::query()->insertOrIgnore([
                'shop_product_id' => $listing->getKey(),
                'shop_id' => $listing->shop_id,
                'on_hand' => 0,
                'low_stock_threshold' => 0,
                'updated_at' => now(),
            ]);
        }

        return ListingStock::query()->whereKey($listing->getKey())->lockForUpdate()->first();
    }

    private function alertLowStock(ShopProduct $listing, int $onHand, int $threshold): void
    {
        $shop = $listing->shop;
        $url = route('workspace.stock.show', [$shop, $listing]);

        foreach ($this->memberships->shopOwners($shop) as $owner) {
            $owner->notify(new SystemNotice(
                __('Low stock'),
                __(':label is down to :count (alert at :threshold).', ['label' => $this->label($listing), 'count' => $onHand, 'threshold' => $threshold]),
                $url,
            ));
        }
    }

    private function lowStockQuery(Shop $shop)
    {
        return ListingStock::query()->where('shop_id', $shop->getKey())
            ->where('low_stock_threshold', '>', 0)
            ->whereColumn('on_hand', '<=', 'low_stock_threshold');
    }

    private function label(ShopProduct $listing): string
    {
        $variant = $listing->loadMissing('variant.product')->variant;

        return $variant->label === '' ? $variant->product->name : "{$variant->product->name} - {$variant->label}";
    }

    private function authorize(User $actor, ShopProduct $listing): void
    {
        Gate::forUser($actor)->authorize(ShopArea::Stock->ability(), $listing->loadMissing('shop')->shop);
    }

    private function ensureQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new BusinessRuleViolation('stock.invalid_quantity', __('Enter a whole number from 1 to :max.', ['max' => self::MAX_QUANTITY]));
        }
    }

    private function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : mb_substr($reason, 0, 200);
    }
}
