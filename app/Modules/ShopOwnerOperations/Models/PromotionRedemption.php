<?php

namespace App\Modules\ShopOwnerOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of a promotion by one order. It is released (not deleted) when the order is
 * declined, cancelled or expires, so the promotion's counter can be given back once.
 */
#[Table(name: 'promotion_redemptions', key: 'promotion_redemption_id', timestamps: false)]
#[Fillable(['shop_promotion_id', 'shop_order_id', 'user_id', 'discount_amount', 'created_at', 'released_at'])]
class PromotionRedemption extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<ShopPromotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(ShopPromotion::class, 'shop_promotion_id');
    }

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'created_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }
}
