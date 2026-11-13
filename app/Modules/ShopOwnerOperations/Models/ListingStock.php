<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The quantity on hand of one listing and its low-stock threshold (decision D-014).
 * A listing without a row is not stock-tracked and keeps its owner-set availability.
 * Changes go only through StockService, which writes the matching ledger entry.
 */
#[Table(name: 'listing_stocks', key: 'shop_product_id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['shop_product_id', 'shop_id', 'on_hand', 'low_stock_threshold', 'updated_at'])]
class ListingStock extends Model
{
    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'shop_product_id', 'shop_product_id')->orderByDesc('stock_movement_id');
    }

    /**
     * A threshold of zero switches the alert off.
     */
    public function isLow(): bool
    {
        return $this->low_stock_threshold > 0 && $this->on_hand <= $this->low_stock_threshold;
    }

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'low_stock_threshold' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
