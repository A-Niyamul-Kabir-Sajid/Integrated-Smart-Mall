<?php

namespace App\Modules\PurchaseVerification\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\PurchaseVerification\DTOs\IssuedCredentialData;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use App\Modules\PurchaseVerification\Models\VerificationToken;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Issues, rotates and revokes the credentials that back verified reviews.
 *
 * Use policy: one credential per purchase line (a quantity of three still allows
 * one verified review of that listing), valid for 90 days unless the staff member
 * chooses another expiry of at most one year, and redeemable once. The raw secret
 * is returned a single time and never stored, logged or audited.
 */
class VerificationCredentialService
{
    public const DEFAULT_VALIDITY_DAYS = 90;

    public const MAX_VALIDITY_DAYS = 365;

    public function __construct(
        private CredentialSecrets $secrets,
        private AuditLogger $audit,
    ) {}

    /**
     * Issues the credential for a purchase line of a completed sale.
     */
    public function issueCredential(User $issuer, PurchaseItem $item, ?CarbonInterface $expiresAt = null): IssuedCredentialData
    {
        $purchase = $item->purchase;
        $shop = $purchase->shop;
        Gate::forUser($issuer)->authorize(Ability::WorkInShop->value, $shop);

        $this->ensureCompleted($purchase->status);
        $expiry = $this->expiry($expiresAt);

        if (VerificationToken::query()->where('purchase_item_id', $item->getKey())->exists()) {
            throw new BusinessRuleViolation('verification.already_issued', __('This purchase line already has a credential; reissue it to replace the secret.'));
        }

        $secret = $this->secrets->generate();

        try {
            $token = VerificationToken::query()->create([
                'purchase_item_id' => $item->getKey(),
                'token_digest' => $this->secrets->digest($secret),
                'issued_by_user_id' => $issuer->getKey(),
                'issued_at' => now(),
                'expires_at' => $expiry,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('verification.already_issued', __('This purchase line already has a credential; reissue it to replace the secret.'));
        }

        $this->audit->record('verification.credential_issued', $token, [
            'purchase_item_id' => $item->getKey(),
            'expires_at' => $expiry->toIso8601String(),
        ], $shop->mall_id, $shop->getKey());

        return new IssuedCredentialData($token, $secret);
    }

    /**
     * Replaces the secret of a credential. The old secret stops
     * working immediately, the expiry restarts, and a revoked credential becomes usable again.
     */
    public function reissueCredential(User $issuer, VerificationToken $token, ?CarbonInterface $expiresAt = null): IssuedCredentialData
    {
        $item = $token->purchaseItem;
        $purchase = $item->purchase;
        $shop = $purchase->shop;
        Gate::forUser($issuer)->authorize(Ability::WorkInShop->value, $shop);

        $this->ensureCompleted($purchase->status);
        $expiry = $this->expiry($expiresAt);
        $secret = $this->secrets->generate();

        DB::transaction(function () use ($token, $issuer, $secret, $expiry): void {
            $locked = VerificationToken::query()->lockForUpdate()->findOrFail($token->getKey());
            $locked->forceFill([
                'token_digest' => $this->secrets->digest($secret),
                'issued_by_user_id' => $issuer->getKey(),
                'issued_at' => now(),
                'expires_at' => $expiry,
                'revoked_at' => null,
            ])->save();

            $token->setRawAttributes($locked->getAttributes(), true);
        });

        $this->audit->record('verification.credential_reissued', $token, [
            'purchase_item_id' => $item->getKey(),
            'expires_at' => $expiry->toIso8601String(),
        ], $shop->mall_id, $shop->getKey());

        return new IssuedCredentialData($token, $secret);
    }

    /**
     * Invalidates a credential. Revoking twice changes nothing.
     */
    public function revokeCredential(User $actor, VerificationToken $token): VerificationToken
    {
        $shop = $token->purchaseItem->purchase->shop;
        Gate::forUser($actor)->authorize(Ability::WorkInShop->value, $shop);

        $revoked = false;

        DB::transaction(function () use ($token, &$revoked): void {
            $locked = VerificationToken::query()->lockForUpdate()->findOrFail($token->getKey());

            if ($locked->revoked_at === null) {
                $locked->forceFill(['revoked_at' => now()])->save();
                $token->setRawAttributes($locked->getAttributes(), true);
                $revoked = true;
            }
        });

        if ($revoked) {
            $this->audit->record('verification.credential_revoked', $token, ['purchase_item_id' => $token->purchase_item_id], $shop->mall_id, $shop->getKey());
        }

        return $token;
    }

    private function ensureCompleted(PurchaseStatus $status): void
    {
        if ($status !== PurchaseStatus::Completed) {
            throw new BusinessRuleViolation('verification.purchase_not_completed', __('Credentials are only issued for completed sales.'));
        }
    }

    private function expiry(?CarbonInterface $expiresAt): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $expiry = $expiresAt === null ? $now->addDays(self::DEFAULT_VALIDITY_DAYS) : CarbonImmutable::instance($expiresAt);

        if ($expiry->lessThanOrEqualTo($now) || $expiry->greaterThan($now->addDays(self::MAX_VALIDITY_DAYS))) {
            throw new BusinessRuleViolation('verification.invalid_expiry', __('Choose an expiry between now and :days days from now.', ['days' => self::MAX_VALIDITY_DAYS]));
        }

        return $expiry;
    }
}
