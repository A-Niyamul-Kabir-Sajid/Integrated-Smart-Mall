<?php

namespace App\Modules\PurchaseVerification\DTOs;

use App\Modules\PurchaseVerification\Enums\VerificationFailure;
use Carbon\CarbonImmutable;

/**
 * The answer of the purchase-verification contract: whether a credential is usable by a
 * customer, and which purchase evidence it backs. It never carries the secret or its digest.
 *
 * The evidence ids are only filled in when the customer is entitled to know them
 * (a usable or just-redeemed credential), so a rejection reveals nothing about the purchase.
 */
final readonly class VerificationResultData
{
    public function __construct(
        public bool $eligible,
        public ?VerificationFailure $failure = null,
        public ?string $verificationTokenId = null,
        public ?string $purchaseId = null,
        public ?string $purchaseItemId = null,
        public ?string $shopProductId = null,
        public ?string $shopId = null,
        public ?CarbonImmutable $redeemedAt = null,
    ) {}

    public static function rejected(VerificationFailure $failure): self
    {
        return new self(eligible: false, failure: $failure);
    }
}
