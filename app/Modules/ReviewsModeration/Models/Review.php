<?php

namespace App\Modules\ReviewsModeration\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * REVIEW: one customer's review of a shop (no listing) or of one shop listing.
 *
 * A user keeps one persistent review per target: edits and withdrawals change this
 * row. Author and target never change. The verified badge is not a column; it is
 * derived from the review's purchase verification (see isVerified()).
 */
#[Table(name: 'reviews', key: 'review_id')]
#[Fillable(['author_user_id', 'shop_id', 'shop_product_id', 'rating', 'title', 'body', 'status'])]
#[UseFactory(ReviewFactory::class)]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (Review $review): void {
            foreach (['author_user_id', 'shop_id', 'shop_product_id'] as $attribute) {
                if ($review->isDirty($attribute)) {
                    throw new LogicException("A review's {$attribute} never changes.");
                }
            }

            if ($review->isDirty('status')) {
                $from = ReviewStatus::from($review->getRawOriginal('status'));

                if (! in_array($review->status, $from->allowedTransitions(), true)) {
                    throw new LogicException("A {$from->value} review cannot become {$review->status->value}.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Reviews are withdrawn or hidden, never deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    /**
     * @return HasOne<ReviewVerification, $this>
     */
    public function verification(): HasOne
    {
        return $this->hasOne(ReviewVerification::class, 'review_id');
    }

    /**
     * @return HasMany<ReviewReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ReviewReply::class, 'review_id')->orderBy('created_at');
    }

    /**
     * @return HasMany<ReviewReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class, 'review_id');
    }

    /**
     * @return HasMany<ModerationAction, $this>
     */
    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class, 'review_id')->orderBy('created_at');
    }

    public function isShopReview(): bool
    {
        return $this->shop_product_id === null;
    }

    /**
     * Whether the review currently carries a purchase-verified badge: it has a binding
     * to a redeemed credential and that binding has not been revoked.
     */
    public function isVerified(): bool
    {
        $verification = $this->verification;

        return $verification !== null && $verification->revoked_at === null;
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Published->value);
    }

    #[Scope]
    protected function verified(Builder $query): Builder
    {
        return $query->whereHas('verification', fn (Builder $binding) => $binding->whereNull('revoked_at'));
    }

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
        ];
    }
}
