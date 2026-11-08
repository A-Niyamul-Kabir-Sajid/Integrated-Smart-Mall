<?php

namespace App\Modules\PurchaseVerification\Models;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * TOKEN_REDEMPTION: the single successful claim of a verification token. Its primary
 * key is the token id, so a token can be claimed once; the row is never changed.
 */
#[Table(name: 'token_redemptions', key: 'verification_token_id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['verification_token_id', 'user_id', 'redeemed_at'])]
class TokenRedemption extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A redemption is final and cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('A redemption is final and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<VerificationToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(VerificationToken::class, 'verification_token_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'immutable_datetime',
        ];
    }
}
