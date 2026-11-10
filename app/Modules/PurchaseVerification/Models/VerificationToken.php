<?php

namespace App\Modules\PurchaseVerification\Models;

use App\Modules\AccountsAccess\Models\User;
use Database\Factories\VerificationTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * VERIFICATION_TOKEN: the credential that lets the buyer of one purchase line
 * write one verified review. The row holds the digest of the secret, never the
 * secret, and the digest is hidden from serialisation.
 */
#[Table(name: 'verification_tokens', key: 'verification_token_id', timestamps: false)]
#[Fillable(['purchase_item_id', 'token_digest', 'issued_by_user_id', 'issued_at', 'expires_at', 'revoked_at'])]
#[Hidden(['token_digest'])]
#[UseFactory(VerificationTokenFactory::class)]
class VerificationToken extends Model
{
    /** @use HasFactory<VerificationTokenFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (VerificationToken $token): void {
            if ($token->isDirty('purchase_item_id')) {
                throw new LogicException('A credential never moves to another purchase line.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Credentials are revoked, never deleted.');
        });
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /**
     * @return HasOne<TokenRedemption, $this>
     */
    public function redemption(): HasOne
    {
        return $this->hasOne(TokenRedemption::class, 'verification_token_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
