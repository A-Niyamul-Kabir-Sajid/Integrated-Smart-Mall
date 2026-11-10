<?php

namespace App\Modules\PurchaseVerification\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * PURCHASE: one sale recorded by shop staff and used as purchase evidence.
 *
 * Once the sale leaves draft its shop, buyer, receipt reference, currency, total
 * and times are immutable; only the status may move along its allowed transitions.
 */
#[Table(name: 'purchases', key: 'purchase_id', timestamps: false)]
#[Fillable(['shop_id', 'buyer_user_id', 'external_receipt_ref', 'status', 'currency', 'total_amount', 'purchased_at', 'recorded_at'])]
#[UseFactory(PurchaseFactory::class)]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    private const IMMUTABLE_AFTER_COMPLETION = ['shop_id', 'buyer_user_id', 'external_receipt_ref', 'currency', 'total_amount', 'purchased_at', 'recorded_at'];

    protected static function booted(): void
    {
        static::updating(function (Purchase $purchase): void {
            $original = PurchaseStatus::from($purchase->getRawOriginal('status'));

            if (! $original->isLocked()) {
                return;
            }

            foreach (self::IMMUTABLE_AFTER_COMPLETION as $attribute) {
                if ($purchase->isDirty($attribute)) {
                    throw new LogicException("Purchase {$attribute} cannot change after the sale is recorded.");
                }
            }

            if ($purchase->isDirty('status') && ! in_array($purchase->status, $original->allowedTransitions(), true)) {
                throw new LogicException("A {$original->value} purchase cannot become {$purchase->status->value}.");
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Purchases are evidence and are never deleted; void them instead.');
        });
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id')->orderBy('line_no');
    }

    #[Scope]
    protected function completed(Builder $query): Builder
    {
        return $query->where('status', PurchaseStatus::Completed->value);
    }

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'total_amount' => 'decimal:2',
            'purchased_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
