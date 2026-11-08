<?php

namespace App\Modules\PurchaseVerification\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\PurchaseVerification\Contracts\PurchaseVerifier;
use App\Modules\PurchaseVerification\DTOs\VerificationResultData;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Enums\VerificationFailure;
use App\Modules\PurchaseVerification\Models\TokenRedemption;
use App\Modules\PurchaseVerification\Models\VerificationToken;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Decides whether a customer may use a purchase credential for a verified review, and
 * claims it exactly once.
 *
 * Redemption runs in one transaction: the token row is locked, every rule is checked
 * against the locked row, then the redemption row is inserted. The redemption table's
 * primary key is the token id, so even if the lock were bypassed the database accepts
 * only one claim and the loser gets "already redeemed".
 */
class TokenVerificationService implements PurchaseVerifier
{
    public function __construct(
        private CredentialSecrets $secrets,
        private AuditLogger $audit,
    ) {}

    public function checkEligibility(string $customerId, #[SensitiveParameter] string $secret, ?string $shopProductId = null): VerificationResultData
    {
        [$token, $failure] = $this->evaluate($customerId, $secret, $shopProductId, lock: false);

        return $failure === null ? $this->eligible($token) : VerificationResultData::rejected($failure);
    }

    public function redeem(string $customerId, #[SensitiveParameter] string $secret, ?string $shopProductId = null): VerificationResultData
    {
        try {
            return DB::transaction(function () use ($customerId, $secret, $shopProductId): VerificationResultData {
                [$token, $failure] = $this->evaluate($customerId, $secret, $shopProductId, lock: true);

                if ($failure !== null) {
                    throw $this->violation($failure);
                }

                $redemption = TokenRedemption::query()->create([
                    'verification_token_id' => $token->getKey(),
                    'user_id' => $customerId,
                    'redeemed_at' => now(),
                ]);

                $purchase = $token->purchaseItem->purchase;
                $this->audit->record('verification.credential_redeemed', $token, [
                    'purchase_item_id' => $token->purchase_item_id,
                    'customer_id' => $customerId,
                ], $purchase->shop->mall_id, $purchase->shop_id);

                return $this->eligible($token, $redemption->redeemed_at);
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->violation(VerificationFailure::AlreadyRedeemed);
        }
    }

    /**
     * Applies every rule and returns the credential with the first failure, if any.
     * An unknown, malformed or someone else's credential is always UnknownCredential.
     *
     * @return array{0: VerificationToken|null, 1: VerificationFailure|null}
     */
    private function evaluate(string $customerId, #[SensitiveParameter] string $secret, ?string $shopProductId, bool $lock): array
    {
        $customer = Str::isUuid($customerId) ? User::query()->find($customerId) : null;

        if ($customer === null || ! $customer->isActive()) {
            return [null, VerificationFailure::CustomerNotAllowed];
        }

        if (! $this->secrets->looksValid($secret)) {
            return [null, VerificationFailure::UnknownCredential];
        }

        $query = VerificationToken::query()->where('token_digest', $this->secrets->digest($secret));
        $token = ($lock ? $query->lockForUpdate() : $query)->first();

        if ($token === null) {
            return [null, VerificationFailure::UnknownCredential];
        }

        $item = $token->purchaseItem()->with('purchase.shop')->first();
        $token->setRelation('purchaseItem', $item);
        $purchase = $item->purchase;

        if ($purchase->buyer_user_id !== null && $purchase->buyer_user_id !== $customer->getKey()) {
            return [null, VerificationFailure::UnknownCredential];
        }

        $failure = match (true) {
            $shopProductId !== null && $item->shop_product_id !== $shopProductId => VerificationFailure::WrongScope,
            TokenRedemption::query()->whereKey($token->getKey())->exists() => VerificationFailure::AlreadyRedeemed,
            $token->isRevoked() => VerificationFailure::Revoked,
            $purchase->status !== PurchaseStatus::Completed => VerificationFailure::PurchaseNotCompleted,
            $token->isExpired() => VerificationFailure::Expired,
            default => null,
        };

        return [$token, $failure];
    }

    private function eligible(VerificationToken $token, ?CarbonImmutable $redeemedAt = null): VerificationResultData
    {
        $item = $token->purchaseItem;

        return new VerificationResultData(
            eligible: true,
            verificationTokenId: $token->getKey(),
            purchaseId: $item->purchase_id,
            purchaseItemId: $item->getKey(),
            shopProductId: $item->shop_product_id,
            shopId: $item->purchase->shop_id,
            redeemedAt: $redeemedAt,
        );
    }

    private function violation(VerificationFailure $failure): BusinessRuleViolation
    {
        return new BusinessRuleViolation($failure->rule(), $failure->message());
    }
}
