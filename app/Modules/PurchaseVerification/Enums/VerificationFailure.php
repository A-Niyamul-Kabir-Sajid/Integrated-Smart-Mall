<?php

namespace App\Modules\PurchaseVerification\Enums;

/**
 * Why a credential cannot back a verified review. The value is the stable rule code
 * (`verification.<value>`) that callers and tests can rely on.
 *
 * Unknown, malformed and someone else's credentials all answer with UnknownCredential,
 * so a guesser cannot tell a real credential of another customer from a wrong one.
 */
enum VerificationFailure: string
{
    case UnknownCredential = 'unknown_credential';
    case WrongScope = 'wrong_scope';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case AlreadyRedeemed = 'already_redeemed';
    case PurchaseNotCompleted = 'purchase_not_completed';
    case CustomerNotAllowed = 'customer_not_allowed';

    public function rule(): string
    {
        return 'verification.'.$this->value;
    }

    public function message(): string
    {
        return match ($this) {
            self::UnknownCredential => __('This purchase code is not valid.'),
            self::WrongScope => __('This purchase code is for a different product.'),
            self::Expired => __('This purchase code has expired.'),
            self::Revoked => __('This purchase code is no longer valid.'),
            self::AlreadyRedeemed => __('This purchase code has already been used.'),
            self::PurchaseNotCompleted => __('The purchase behind this code is not completed.'),
            self::CustomerNotAllowed => __('Your account cannot use purchase codes.'),
        };
    }
}
