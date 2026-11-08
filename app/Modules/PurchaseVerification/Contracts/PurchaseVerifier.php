<?php

namespace App\Modules\PurchaseVerification\Contracts;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\PurchaseVerification\DTOs\VerificationResultData;
use SensitiveParameter;

/**
 * What ReviewsModeration (and any other module) may ask PurchaseVerification: does this
 * customer hold a usable purchase credential, and claim it for a verified review.
 *
 * Both methods take the raw secret the customer typed or scanned. Neither returns,
 * logs or audits it. The customer id is the signed-in account, never a value the
 * customer supplies.
 */
interface PurchaseVerifier
{
    /**
     * Checks a credential without using it up, for example to show "valid" before the review form is sent.
     * A rejection is returned in the result, not thrown.
     *
     * @param  string|null  $shopProductId  Limit the check to credentials for this shop listing
     */
    public function checkEligibility(string $customerId, #[SensitiveParameter] string $secret, ?string $shopProductId = null): VerificationResultData;

    /**
     * Claims the credential for the customer, once. The claim is atomic: when two requests
     * present the same credential at the same time exactly one succeeds.
     *
     * @param  string|null  $shopProductId  Claim only a credential for this shop listing
     *
     * @throws BusinessRuleViolation with rule `verification.<failure>` when the credential cannot be claimed
     */
    public function redeem(string $customerId, #[SensitiveParameter] string $secret, ?string $shopProductId = null): VerificationResultData;
}
