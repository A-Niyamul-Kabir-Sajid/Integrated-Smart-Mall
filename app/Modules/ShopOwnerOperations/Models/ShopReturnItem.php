<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\PurchaseVerification\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a return: how many of one sale line come back and what they are worth.
 */
#[Table(name: 'shop_return_items', key: 'shop_return_item_id', timestamps: false)]
#[Fillable(['shop_return_id', 'purchase_item_id', 'quantity', 'value_amount'])]
class ShopReturnItem extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Return lines are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Return lines are never deleted; cancel the return instead.');
        });
    }

    /**
     * @return BelongsTo<ShopReturn, $this>
     */
    public function shopReturn(): BelongsTo
    {
        return $this->belongsTo(ShopReturn::class, 'shop_return_id');
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }

    protected function casts(): array
    {
        return [
            'value_amount' => 'decimal:2',
        ];
    }
}
