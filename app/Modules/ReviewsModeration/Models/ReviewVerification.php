<?php

namespace App\Modules\ReviewsModeration\Models;

use App\Modules\PurchaseVerification\Models\TokenRedemption;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * REVIEW_VERIFICATION: binds one product review to one redeemed purchase credential.
 * The binding is kept when the review is withdrawn, so the credential cannot verify a
 * second review; only revocation (refund, void or moderator decision) ever changes it.
 */
#[Table(name: 'review_verifications', key: 'review_id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['review_id', 'verification_token_id', 'verified_at', 'revoked_at', 'revocation_reason'])]
class ReviewVerification extends Model
{
    protected static function booted(): void
    {
        static::updating(function (ReviewVerification $verification): void {
            foreach (['review_id', 'verification_token_id', 'verified_at'] as $attribute) {
                if ($verification->isDirty($attribute)) {
                    throw new LogicException("A verification's {$attribute} never changes.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Verification evidence is revoked, never deleted.');
        });
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'review_id');
    }

    /**
     * @return BelongsTo<TokenRedemption, $this>
     */
    public function redemption(): BelongsTo
    {
        return $this->belongsTo(TokenRedemption::class, 'verification_token_id');
    }

    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
