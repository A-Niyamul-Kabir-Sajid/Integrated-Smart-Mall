<?php

namespace App\Modules\PurchaseVerification\Services;

use SensitiveParameter;

/**
 * Creates and fingerprints purchase-verification secrets.
 *
 * A secret is 256 bits from the operating system's random generator, so it cannot
 * be predicted from a product, shop or purchase id. Only its SHA-256 digest is
 * stored; for a secret this long a plain digest is enough, because there is
 * nothing to guess and nothing to brute-force.
 */
class CredentialSecrets
{
    public const PREFIX = 'ismv_';

    public function generate(): string
    {
        return self::PREFIX.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function digest(#[SensitiveParameter] string $secret): string
    {
        return hash('sha256', trim($secret));
    }

    /**
     * Whether the text could be a secret this class produced, so obviously wrong input is
     * refused before any database lookup.
     */
    public function looksValid(#[SensitiveParameter] string $secret): bool
    {
        return preg_match('/^'.self::PREFIX.'[A-Za-z0-9_-]{43}$/', trim($secret)) === 1;
    }
}
