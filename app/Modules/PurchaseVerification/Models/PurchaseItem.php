<?php

namespace App\Modules\PurchaseVerification\Models;

use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * PURCHASE_ITEM: one line of a sale, with the label and price as they were at sale
 * time. A line is written once with its purchase and never edited or removed.
 */
#[Table(name: 'purchase_items', key: 'purchase_item_id', timestamps: false)]
#[Fillable(['purchase_id', 'line_no', 'shop_product_id', 'product_label_snapshot', 'quantity', 'unit_price_amount', 'line_discount_amount', 'line_total_amount'])]
#[UseFactory(PurchaseItemFactory::class)]
class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Purchase lines are immutable snapshots.');
        });

        static::deleting(function (): never {
            throw new LogicException('Purchase lines are never deleted; void the purchase instead.');
        });
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
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
            'line_discount_amount' => 'decimal:2',
            'line_total_amount' => 'decimal:2',
        ];
    }
}
