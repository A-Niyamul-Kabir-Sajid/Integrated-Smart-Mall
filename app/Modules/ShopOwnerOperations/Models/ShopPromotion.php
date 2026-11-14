<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\DiscountType;
use Database\Factories\ShopPromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount offered by one shop (decision D-014). With a coupon code it applies when the
 * customer enters the code; without one it applies to every order while it runs.
 */
#[Table(name: 'shop_promotions', key: 'shop_promotion_id')]
#[Fillable(['shop_id', 'title', 'description', 'discount_type', 'discount_value', 'coupon_code', 'min_subtotal', 'starts_at', 'ends_at', 'usage_limit', 'per_customer_limit', 'times_redeemed', 'is_paused', 'created_by_user_id'])]
#[UseFactory(ShopPromotionFactory::class)]
class ShopPromotion extends Model
{
    /** @use HasFactory<ShopPromotionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return HasMany<PromotionRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class, 'shop_promotion_id');
    }

    /**
     * Whether the promotion can be used right now: not paused, inside its dates and not used up.
     */
    public function isRunning(): bool
    {
        return ! $this->is_paused
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture()
            && ($this->usage_limit === null || $this->times_redeemed < $this->usage_limit);
    }

    public function stateLabel(): string
    {
        return match (true) {
            $this->ends_at->isPast() => __('Ended'),
            $this->is_paused => __('Paused'),
            $this->starts_at->isFuture() => __('Scheduled'),
            $this->usage_limit !== null && $this->times_redeemed >= $this->usage_limit => __('Used up'),
            default => __('Running'),
        };
    }

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'times_redeemed' => 'integer',
            'is_paused' => 'boolean',
        ];
    }
}
