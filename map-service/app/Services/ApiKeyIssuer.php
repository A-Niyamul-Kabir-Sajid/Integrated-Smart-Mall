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
     * @param  list<string>  $allowedOrigins  web origins that may frame the embedded map
     * @return array{0: ApiKey, 1: string} the stored key and the plain key to show once
     */
    public function issue(string $name, array $abilities, ?Carbon $expiresAt = null, array $allowedOrigins = []): array
    {
        $origins = $this->normaliseOrigins($allowedOrigins);
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
            'allowed_origins' => $origins,
            'expires_at' => $expiresAt,
        ]);

        return [$key, $plain];
    }

    /**
     * Replace the origins that may frame this key's embedded map.
     *
     * @param  list<string>  $origins
     */
    public function setOrigins(ApiKey $key, array $origins): void
    {
        $key->forceFill(['allowed_origins' => $this->normaliseOrigins($origins)])->save();
    }

    /**
     * Each origin as scheme://host[:port], lower case, without a path.
     *
     * @param  list<string>  $origins
     * @return list<string>
     *
     * @throws InvalidArgumentException for anything that is not an http(s) origin
     */
    public function normaliseOrigins(array $origins): array
    {
        $clean = [];
        foreach ($origins as $origin) {
            $origin = trim((string) $origin);
            if ($origin === '') {
                continue;
            }
            $parts = parse_url($origin);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower((string) ($parts['host'] ?? ''));
            if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! preg_match('/^[a-z0-9.\-]+$/', $host)
                || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
                throw new InvalidArgumentException("“{$origin}” is not an origin; write it like https://mall.example.com or http://127.0.0.1:8000.");
            }
            $clean[] = $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
        }

        return array_values(array_unique($clean));
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
