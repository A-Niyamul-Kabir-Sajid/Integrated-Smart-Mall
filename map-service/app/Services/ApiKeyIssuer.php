<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates and revokes API keys. A key looks like ism_<40 random characters>;
 * only its SHA-256 hash is stored.
 */
class ApiKeyIssuer
{
    /**
     * @param  list<string>  $abilities
     * @return array{0: ApiKey, 1: string} the stored key and the plain key to show once
     */
    public function issue(string $name, array $abilities, ?Carbon $expiresAt = null): array
    {
        $abilities = array_values(array_unique($abilities));
        if ($abilities === [] || array_diff($abilities, array_keys(ApiKey::ABILITIES)) !== []) {
            throw new InvalidArgumentException('Choose abilities from: '.implode(', ', array_keys(ApiKey::ABILITIES)).'.');
        }

        $plain = 'ism_'.Str::random(40);
        $key = ApiKey::create([
            'name' => $name,
            'prefix' => substr($plain, 0, 12),
            'key_hash' => ApiKey::hashOf($plain),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return [$key, $plain];
    }

    public function revoke(ApiKey $key): void
    {
        if ($key->revoked_at === null) {
            $key->forceFill(['revoked_at' => now()])->save();
        }
    }

    /**
     * The active key matching $plain, or null.
     */
    public function find(string $plain): ?ApiKey
    {
        if (! str_starts_with($plain, 'ism_') || strlen($plain) !== 44) {
            return null;
        }
        $key = ApiKey::query()->where('key_hash', ApiKey::hashOf($plain))->first();

        return $key !== null && $key->isActive() ? $key : null;
    }
}
