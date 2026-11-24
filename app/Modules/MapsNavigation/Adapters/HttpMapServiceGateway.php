<?php

namespace App\Modules\MapsNavigation\Adapters;

use App\Modules\MapsNavigation\Contracts\MapServiceGateway;
use App\Modules\MapsNavigation\Contracts\MapServiceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Calls the map service's /api/v1 with the key from MAP_SERVICE_KEY.
 */
class HttpMapServiceGateway implements MapServiceGateway
{
    /**
     * @param  array{url?: string|null, key?: string|null, timeout?: int}  $config  config('services.map_service')
     */
    public function __construct(private array $config) {}

    public function maps(): array
    {
        return $this->get('/api/v1/maps')['data'] ?? [];
    }

    public function package(string $mapId): array
    {
        return $this->get('/api/v1/maps/'.rawurlencode($mapId));
    }

    public function annotations(string $mapId, array $filters = []): array
    {
        $query = array_filter(array_intersect_key($filters, array_flip(['floor', 'unit_code', 'kind'])), fn ($value): bool => $value !== null && $value !== '');

        return $this->get('/api/v1/maps/'.rawurlencode($mapId).'/annotations', $query)['data'] ?? [];
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->client()->get($path, $query);
        } catch (ConnectionException) {
            throw new MapServiceUnavailable("The map service at {$this->config['url']} is not reachable. Is it running?");
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw new MapServiceUnavailable($this->explain($response), $response->status());
    }

    private function client(): PendingRequest
    {
        if (empty($this->config['url']) || empty($this->config['key'])) {
            throw new MapServiceUnavailable('The map service is not configured: set MAP_SERVICE_URL and MAP_SERVICE_KEY in .env.');
        }

        return Http::baseUrl(rtrim((string) $this->config['url'], '/'))
            ->withToken((string) $this->config['key'])
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->retry(2, 200, fn (\Throwable $error): bool => $error instanceof ConnectionException, throw: false);
    }

    private function explain(Response $response): string
    {
        $detail = $response->json('message');

        return match ($response->status()) {
            401 => 'The map service refused the API key (unknown, revoked or expired). Create a new key on its API keys page.',
            403 => 'The API key lacks permission for this request'.($detail ? ": {$detail}" : '.'),
            404 => 'The map service has no published map with that id.',
            429 => 'Too many requests to the map service; try again in a minute.',
            default => "The map service answered HTTP {$response->status()}".($detail ? ": {$detail}" : '.'),
        };
    }
}
