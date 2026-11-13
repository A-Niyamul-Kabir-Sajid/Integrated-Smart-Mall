<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ShopOwnerOperations\Enums\ReturnKind;
use App\Modules\ShopOwnerOperations\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A request to take back goods of one recorded sale (decision D-014). The kind, the sale,
 * the reason and the value are fixed once requested; only the status, the decision note
 * and the restock flag change afterwards.
 */
#[Table(name: 'shop_returns', key: 'shop_return_id')]
#[Fillable(['return_number', 'shop_id', 'purchase_id', 'requested_by_user_id', 'kind', 'status', 'reason', 'decision_note', 'value_amount', 'restocked', 'decided_at', 'completed_at'])]
class ShopReturn extends Model
{
    use HasUuids;

    /** @var list<string> */
    private const FROZEN = ['return_number', 'shop_id', 'purchase_id', 'requested_by_user_id', 'kind', 'reason', 'value_amount'];

    protected static function booted(): void
    {
        static::updating(function (ShopReturn $return): void {
            foreach (self::FROZEN as $attribute) {
                if ($return->isDirty($attribute)) {
                    throw new LogicException("Return {$attribute} cannot change after the request.");
                }
            }

            if ($return->isDirty('status')) {
                $from = ReturnStatus::from($return->getRawOriginal('status'));

                if (! in_array($return->status, $from->allowedTransitions(), true)) {
                    throw new LogicException("A {$from->value} return cannot become {$return->status->value}.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Returns are never deleted; cancel them instead.');
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
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return HasMany<ShopReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopReturnItem::class, 'shop_return_id');
    }

    /**
     * @return HasMany<ShopReturnEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShopReturnEvent::class, 'shop_return_id')->orderBy('shop_return_event_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => ReturnKind::class,
            'status' => ReturnStatus::class,
            'value_amount' => 'decimal:2',
            'restocked' => 'boolean',
            'decided_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
