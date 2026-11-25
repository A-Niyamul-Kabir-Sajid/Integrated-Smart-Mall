<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Models\User;
use App\Services\ApiKeyIssuer;
use App\Services\CheckpointRegistry;
use App\Services\MapPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * ISM-65: the AR directions page. An open embedded map asks for a signed
 * link with its session token; the page opens in its own tab, is never
 * framed, and reads the map (with where each QR code hangs) and routes with
 * its own session token.
 */
class ArDirectionsTest extends TestCase
{
    use RefreshDatabase;

    private const POLICY = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'none'";

    private MallMap $map;

    private ApiKey $key;

    private string $plain;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->map = $this->pilotMap();
        [$this->key, $this->plain] = app(ApiKeyIssuer::class)->issue('Smart Mall app', ['maps:read'], null, ['http://127.0.0.1:8000']);
        $this->slug = Checkpoint::query()->where('checkpoint_code', 'G-C01')->value('public_slug');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(TestResponse $page, string $id): array
    {
        preg_match('#<script type="application/json" id="'.$id.'">(.*?)</script>#s', $page->getContent(), $match);

        return json_decode(html_entity_decode($match[1] ?? '{}'), true);
    }

    /**
     * The session token of an embedded map opened the way the Smart Mall app opens it.
     */
    private function embedToken(?MallMap $map = null): string
    {
        $url = $this->withToken($this->plain)->postJson('/api/v1/embed-links', ['map_id' => ($map ?? $this->map)->id])->assertCreated()->json('data.url');

        return $this->config($this->get($url), 'embed-config')['token'];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function arLink(array $query = []): TestResponse
    {
        return $this->withHeader('X-Embed-Token', $this->embedToken())
            ->getJson(route('embed.ar-link', $this->map).'?'.http_build_query($query + ['to' => 'unit:1-205']));
    }

    public function test_an_open_embedded_map_asks_for_an_ar_link_with_its_session_token(): void
    {
        $url = $this->withToken($this->plain)->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall'])->json('data.url');
        $this->assertSame(route('embed.ar-link', $this->map), $this->config($this->get($url), 'embed-config')['arLinkUrl']);

        $response = $this->arLink(['from' => "checkpoint:{$this->slug}", 'step_free' => 1, 'lang' => 'bn'])->assertOk();

        $link = $response->json('data.url');
        $this->assertStringStartsWith(url("/embed/maps/{$this->map->id}/ar"), $link);
        $this->assertStringContainsString('signature=', $link);
        $this->assertStringContainsString('to=unit%3A1-205', $link);
        $this->assertStringNotContainsString($this->plain, $link);
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_ar_links_are_refused_without_a_session_or_with_places_that_are_not_places(): void
    {
        $this->getJson(route('embed.ar-link', $this->map).'?to=unit:1-205')->assertUnauthorized()->assertJsonPath('reason', 'session');
        $this->withHeader('X-Embed-Token', $this->embedToken($this->pilotMap('other-mall')))
            ->getJson(route('embed.ar-link', $this->map).'?to=unit:1-205')->assertUnauthorized();

        $this->arLink(['to' => ''])->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->arLink(['to' => 'G-101'])->assertUnprocessable()->assertJsonPath('reason', 'unknown_place');
        $this->arLink(['from' => 'the door'])->assertUnprocessable()->assertJsonPath('reason', 'unknown_place')->assertJsonValidationErrors('from');
        $this->arLink(['lang' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('lang');
    }

    public function test_the_ar_page_opens_from_its_link_in_its_own_tab_only(): void
    {
        $link = $this->arLink(['from' => "checkpoint:{$this->slug}", 'step_free' => 1, 'lang' => 'bn'])->json('data.url');

        $page = $this->get($link)
            ->assertOk()
            ->assertHeader('Content-Security-Policy', self::POLICY)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('<html lang="bn">', false)
            ->assertSee('Start AR')
            ->assertSee(asset('ar/ar.js'), false)
            ->assertDontSee($this->plain);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        $config = $this->config($page, 'ar-config');
        $this->assertSame('unit:1-205', $config['to']);
        $this->assertSame("checkpoint:{$this->slug}", $config['from']);
        $this->assertTrue($config['stepFree']);
        $this->assertSame('time', $config['cost']);
        $this->assertSame('bn', $config['lang']);
        $this->assertSame(route('embed.data', $this->map), $config['dataUrl']);

        // The page's own token reads the map and asks for routes from the QR code it lines up with.
        $this->withHeader('X-Embed-Token', $config['token'])->getJson($config['dataUrl'])->assertOk()->assertJsonCount(2, 'data.qr_codes');
        $this->withHeader('X-Embed-Token', $config['token'])
            ->getJson($config['routeUrl'].'?'.http_build_query(['from' => "checkpoint:{$this->slug}", 'to' => ['unit:1-205']]))
            ->assertOk()
            ->assertJsonPath('data.to.0.place', 'unit:1-205');
    }

    public function test_a_tampered_expired_revoked_or_unpublished_ar_link_is_refused(): void
    {
        $link = $this->arLink()->json('data.url');

        $this->get(str_replace('unit%3A1-205', 'unit%3AG-101', $link))->assertForbidden();

        app(MapPublisher::class)->unpublish($this->map);
        $this->get($link)->assertNotFound()->assertSee('This map is not available right now.')->assertHeader('Content-Security-Policy', self::POLICY);

        app(MapPublisher::class)->publish($this->map);
        app(ApiKeyIssuer::class)->revoke($this->key);
        $this->get($link)->assertForbidden()->assertSee('This AR link is no longer valid.')->assertSee('Go back to the map and tap View in AR again.');
    }

    public function test_an_ar_link_must_be_opened_within_its_minutes(): void
    {
        $link = $this->arLink()->json('data.url');

        $this->travel(16)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_the_api_documentation_explains_ar_directions(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('AR directions')
            ->assertSee('allow="fullscreen; xr-spatial-tracking"', false)
            ->assertSee('Print signs at real size');
    }

    public function test_qr_signs_print_at_the_width_the_map_gives_them(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.checkpoints.index'))
            ->assertOk()
            ->assertSee('Print signs at real size')
            ->assertSee('data-width-cm="18"', false)
            ->assertSee('the black square is 18 cm wide. Hang it upright on floor G with its centre 1.40 m above the floor', false);
    }

    public function test_the_map_data_says_where_each_active_qr_code_hangs(): void
    {
        $token = $this->embedToken();

        $data = $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $this->map))->assertOk();
        $data->assertJsonCount(2, 'data.qr_codes')->assertJsonPath('data.qr_codes.0.checkpoint_code', '1-C01');
        $this->assertEquals([
            'slug' => $this->slug,
            'checkpoint_code' => 'G-C01',
            'label' => 'Main entrance, pillar facing the doors',
            'floor_code' => 'G',
            'node_code' => 'G-C01',
            'qr' => ['x' => -0.8, 'y' => 0.0, 'z' => 1.4, 'yaw' => 0.0, 'height' => 1.4, 'width' => 0.18],
        ], $data->json('data.qr_codes.1'));
        $this->assertEquals(['x' => 5.0, 'y' => 10.8, 'z' => 6.4, 'yaw' => 270.0, 'height' => 1.4, 'width' => 0.18], $data->json('data.qr_codes.0.qr'));
        $first = collect($data->json('data.nodes'))->firstWhere('code', '1-C01');
        $this->assertEquals(['code' => '1-C01', 'floor' => '1', 'type' => 'checkpoint', 'x' => 5.0, 'y' => 10.0, 'z' => 5.0], $first);

        // A retired code is no longer offered for lining up.
        app(CheckpointRegistry::class)->retire(Checkpoint::query()->where('checkpoint_code', 'G-C01')->sole());
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $this->map))
            ->assertJsonCount(1, 'data.qr_codes')
            ->assertJsonPath('data.qr_codes.0.checkpoint_code', '1-C01');
    }
}
