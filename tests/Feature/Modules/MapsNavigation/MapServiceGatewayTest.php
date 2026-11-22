<?php

namespace Tests\Feature\Modules\MapsNavigation;

use App\Modules\MapsNavigation\Contracts\MapServiceGateway;
use App\Modules\MapsNavigation\Contracts\MapServiceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapServiceGatewayTest extends TestCase
{
    private const KEY = 'ism_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.map_service' => ['url' => 'http://map-service.test', 'key' => self::KEY, 'timeout' => 5]]);
    }

    public function test_it_reads_published_maps_with_the_api_key(): void
    {
        Http::fake(['map-service.test/api/v1/maps' => Http::response(['data' => [['id' => 'map-1', 'name' => 'Sample floor', 'floor_codes' => ['G'], 'stats' => ['nodes' => 3], 'annotations_count' => 2]]])]);

        $maps = app(MapServiceGateway::class)->maps();

        $this->assertSame('Sample floor', $maps[0]['name']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://map-service.test/api/v1/maps'
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY)
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_it_reads_a_package_and_filtered_annotations(): void
    {
        Http::fake([
            'map-service.test/api/v1/maps/map-1' => Http::response(['format' => 'ism-map-package', 'file_urls' => ['floors/G_model.glb' => 'http://map-service.test/files/signed']]),
            'map-service.test/api/v1/maps/map-1/annotations*' => Http::response(['map_id' => 'map-1', 'data' => [['id' => 'ann-0001', 'text' => 'Cafe Aroma', 'unit_code' => 'G-101']]]),
        ]);
        $gateway = app(MapServiceGateway::class);

        $this->assertSame('ism-map-package', $gateway->package('map-1')['format']);
        $annotations = $gateway->annotations('map-1', ['unit_code' => 'G-101', 'floor' => '', 'ignored' => 'x']);

        $this->assertSame('Cafe Aroma', $annotations[0]['text']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://map-service.test/api/v1/maps/map-1/annotations?unit_code=G-101');
    }

    public function test_refusals_become_clear_errors(): void
    {
        $cases = [
            401 => 'refused the API key',
            403 => 'lacks permission',
            404 => 'no published map with that id',
            429 => 'Too many requests',
            500 => 'answered HTTP 500',
        ];
        $current = 0;
        Http::fake(function () use (&$current) {
            return Http::response(['message' => 'detail'], $current);
        });
        foreach ($cases as $status => $message) {
            $current = $status;
            try {
                app(MapServiceGateway::class)->maps();
                $this->fail("HTTP {$status} was not reported");
            } catch (MapServiceUnavailable $error) {
                $this->assertStringContainsString($message, $error->getMessage());
                $this->assertSame($status, $error->status);
            }
        }
    }

    public function test_an_unreachable_or_unconfigured_service_is_reported(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        try {
            app(MapServiceGateway::class)->maps();
            $this->fail('an unreachable service was not reported');
        } catch (MapServiceUnavailable $error) {
            $this->assertStringContainsString('not reachable', $error->getMessage());
        }

        config(['services.map_service.key' => null]);
        $this->expectExceptionMessage('set MAP_SERVICE_URL and MAP_SERVICE_KEY');
        app(MapServiceGateway::class)->maps();
    }

    public function test_the_check_command_lists_maps_or_explains_the_problem(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['data' => [['id' => 'map-1', 'name' => 'Sample floor', 'floor_codes' => ['G'], 'stats' => ['nodes' => 3, 'checkpoints' => 0], 'annotations_count' => 2]]])
            ->push(['data' => []])
            ->push(['message' => 'The API key is unknown, revoked or expired.'], 401)]);

        $this->artisan('maps:service-check')->expectsOutputToContain('Connected. Published maps:')->expectsOutputToContain('Sample floor')->assertSuccessful();
        $this->artisan('maps:service-check')->expectsOutputToContain('no published maps yet')->assertSuccessful();
        $this->artisan('maps:service-check')->expectsOutputToContain('refused the API key')->assertFailed();
    }
}
