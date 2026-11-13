<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ShopOwnerOperations\Enums\OrderKind;
use App\Modules\ShopOwnerOperations\Enums\OrderStatus;
use Database\Factories\ShopOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A customer's request to reserve or collect goods from one shop (decision D-014).
 * Lines and amounts are snapshots taken when the order was placed; only the status,
 * the shop note and the link to the recorded sale change afterwards.
 */
#[Table(name: 'shop_orders', key: 'shop_order_id')]
#[Fillable(['order_number', 'shop_id', 'customer_user_id', 'kind', 'status', 'currency', 'subtotal_amount', 'discount_amount', 'total_amount', 'coupon_code', 'promotion_title', 'pickup_code', 'pickup_by', 'customer_note', 'shop_note', 'purchase_id', 'completed_at'])]
#[UseFactory(ShopOrderFactory::class)]
class ShopOrder extends Model
{
    /** @use HasFactory<ShopOrderFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    private const FROZEN = ['order_number', 'shop_id', 'customer_user_id', 'kind', 'currency', 'subtotal_amount', 'discount_amount', 'total_amount', 'coupon_code', 'promotion_title', 'pickup_code', 'pickup_by'];

    protected static function booted(): void
    {
        static::updating(function (ShopOrder $order): void {
            foreach (self::FROZEN as $attribute) {
                if ($order->isDirty($attribute)) {
                    throw new LogicException("Order {$attribute} cannot change after the order is placed.");
                }
            }

            if ($order->isDirty('status')) {
                $from = OrderStatus::from($order->getRawOriginal('status'));

                if (! in_array($order->status, $from->allowedTransitions(), true)) {
                    throw new LogicException("A {$from->value} order cannot become {$order->status->value}.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Orders are never deleted; cancel them instead.');
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    /**
     * @return HasMany<ShopOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'shop_order_id')->orderBy('line_no');
    }

    /**
     * @return HasMany<ShopOrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShopOrderEvent::class, 'shop_order_id')->orderBy('shop_order_event_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => OrderKind::class,
            'status' => OrderStatus::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'pickup_by' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
