<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of an order, with the label and price as they were when it was placed.
 */
#[Table(name: 'shop_order_items', key: 'shop_order_item_id', timestamps: false)]
#[Fillable(['shop_order_id', 'line_no', 'shop_product_id', 'label_snapshot', 'quantity', 'unit_price_amount', 'line_total_amount'])]
class ShopOrderItem extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Order lines are immutable snapshots.');
        });

        static::deleting(function (): never {
            throw new LogicException('Order lines are never deleted; cancel the order instead.');
        });
    }

    /**
     * @return BelongsTo<ShopOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    protected function casts(): array
    {
        return [
            'unit_price_amount' => 'decimal:2',
            'line_total_amount' => 'decimal:2',
        ];
    }
}
