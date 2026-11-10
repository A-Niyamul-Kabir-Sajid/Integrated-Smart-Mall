<?php

namespace App\Modules\PurchaseVerification\DTOs;

use App\Modules\PurchaseVerification\Models\VerificationToken;
use SensitiveParameter;

/**
 * A freshly issued credential: the stored record and the raw secret.
 *
 * The secret exists only in this object. Show it to the shop staff once (print or
 * QR) and drop it; it cannot be recovered later, only replaced by reissuing. The
 * secret is kept out of dumps and serialisation so it cannot slip into a log.
 */
final readonly class IssuedCredentialData
{
    public function __construct(
        public VerificationToken $token,
        #[SensitiveParameter] public string $secret,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['token' => $this->token->getKey(), 'secret' => '[hidden]'];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return ['token' => $this->token->getKey()];
    }
}
