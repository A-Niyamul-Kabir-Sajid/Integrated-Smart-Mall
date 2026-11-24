<?php

namespace App\Services;

use App\Exceptions\RouteNotPossible;
use App\Models\ApiKey;
use App\Models\MallMap;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * The embedded map (ISM-64). An app's server asks for a short-lived signed
 * link with its API key and puts it in an iframe; the key never reaches the
 * browser. The page then talks to this service with an encrypted session
 * token that names the map and the key, so revoking the key or unpublishing
 * the map ends every open embed.
 */
class EmbedService
{
    public const LANGUAGES = ['en', 'bn'];

    /**
     * @param  array{from?: string|null, to?: list<string>, step_free?: bool, cost?: string, lang?: string, minutes?: int}  $options
     * @return array{url: string, expires_at: string}
     *
     * @throws InvalidArgumentException when the key may not be framed anywhere
     */
    public function link(ApiKey $key, MallMap $map, array $options): array
    {
        if ($key->origins() === []) {
            throw new InvalidArgumentException('This API key has no sites allowed to show the embedded map. Add the app\'s origin (for example http://127.0.0.1:8000) on the API keys page.');
        }

        $expiresAt = now()->addMinutes(max(1, min(60, (int) ($options['minutes'] ?? config('mapservice.embed_link_minutes', 15)))));
        $parameters = array_filter([
            'mallMap' => $map->id,
            'key' => $key->id,
            'from' => $options['from'] ?? null,
            'to' => $options['to'] ?? [],
            'step_free' => ! empty($options['step_free']) ? 1 : null,
            'cost' => ($options['cost'] ?? 'time') === 'distance' ? 'distance' : null,
            'lang' => in_array($options['lang'] ?? 'en', self::LANGUAGES, true) ? ($options['lang'] ?? 'en') : 'en',
        ], fn ($value): bool => $value !== null && $value !== []);

        return [
            'url' => URL::temporarySignedRoute('embed.show', $expiresAt, $parameters),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * A session token for the page opened from a signed link.
     */
    public function token(ApiKey $key, MallMap $map): string
    {
        return Crypt::encryptString((string) json_encode([
            'm' => $map->id,
            'k' => $key->id,
            'e' => now()->addMinutes((int) config('mapservice.embed_session_minutes', 240))->getTimestamp(),
        ]));
    }

    /**
     * The key and map of a session token, if it is genuine, unexpired, the key
     * is still active and the map is still published.
     *
     * @return array{key: ApiKey, map: MallMap}|null
     */
    public function session(?string $token, ?string $mapId = null): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }
        if (! is_array($payload) || ! isset($payload['m'], $payload['k'], $payload['e']) || Carbon::createFromTimestamp((int) $payload['e'])->isPast()) {
            return null;
        }
        if ($mapId !== null && $mapId !== $payload['m']) {
            return null;
        }

        $key = ApiKey::query()->find($payload['k']);
        $map = MallMap::query()->find($payload['m']);
        if ($key === null || ! $key->isActive() || ! $key->can('maps:read') || $map === null || ! $map->isPublished()) {
            return null;
        }

        return ['key' => $key, 'map' => $map];
    }

    /**
     * The Content-Security-Policy of the embed page: only the key's origins may frame it.
     */
    public function contentSecurityPolicy(ApiKey $key): string
    {
        return "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors ".implode(' ', $key->origins());
    }

    /**
     * Check a start or destination before it is put in a link.
     *
     * @throws RouteNotPossible
     */
    public function assertPlaceSyntax(?string $place, string $field): void
    {
        if ($place !== null && ! preg_match('/^(checkpoint|node|unit|facility|facility-type):.{1,190}$/u', $place)) {
            throw new RouteNotPossible('unknown_place', "Write {$field} as kind:value.", $field);
        }
    }
}
