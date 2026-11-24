<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Models\User;
use App\Services\ApiKeyIssuer;
use App\Services\MapPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * ISM-64: signed embed links, the embedded map page, its session token and
 * the public API documentation.
 */
class EmbedTest extends TestCase
{
    use RefreshDatabase;

    private MallMap $map;

    private ApiKey $key;

    private string $plain;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->map = $this->pilotMap();
        [$this->key, $this->plain] = app(ApiKeyIssuer::class)->issue('Smart Mall app', ['maps:read'], null, ['http://127.0.0.1:8000']);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function link(array $body = []): string
    {
        return $this->withToken($this->plain)->postJson('/api/v1/embed-links', $body + ['mall_ref' => 'demo-mall'])->assertCreated()->json('data.url');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(TestResponse $page): array
    {
        preg_match('#<script type="application/json" id="embed-config">(.*?)</script>#s', $page->getContent(), $match);

        return json_decode(html_entity_decode($match[1] ?? '{}'), true);
    }

    public function test_origins_are_checked_and_written_the_same_way(): void
    {
        $issuer = app(ApiKeyIssuer::class);

        $this->assertSame(['http://example.com:8000', 'https://mall.example'], $issuer->normaliseOrigins(['HTTP://Example.com:8000/', 'https://mall.example', ' ', 'https://mall.example']));
        foreach (['ftp://example.com', 'https://example.com/path', 'example.com', 'https://user@example.com', 'https://example.com?x=1'] as $bad) {
            try {
                $issuer->normaliseOrigins([$bad]);
                $this->fail("{$bad} should be refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_embed_link_is_created_for_the_mall_s_published_map(): void
    {
        $response = $this->withToken($this->plain)->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall', 'from' => 'node:G-C01', 'to' => 'unit:1-205', 'lang' => 'bn'])
            ->assertCreated()
            ->assertJsonPath('data.map_id', $this->map->id)
            ->assertJsonPath('data.mall_ref', 'demo-mall');

        $url = $response->json('data.url');
        $this->assertStringStartsWith(url("/embed/maps/{$this->map->id}"), $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString($this->plain, $url);
    }

    public function test_embed_links_are_refused_without_a_key_a_map_origins_or_good_places(): void
    {
        $this->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall'])->assertUnauthorized();
        $this->withToken($this->plain)->postJson('/api/v1/embed-links', [])->assertUnprocessable()->assertJsonValidationErrors(['mall_ref', 'map_id']);
        $this->withToken($this->plain)->postJson('/api/v1/embed-links', ['mall_ref' => 'no-such-mall'])->assertNotFound()->assertJsonPath('reason', 'no_map');
        $this->withToken($this->plain)->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall', 'to' => 'G-101'])->assertUnprocessable()->assertJsonPath('reason', 'unknown_place');
        $this->withToken($this->plain)->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall', 'lang' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('lang');

        [, $noOrigins] = $this->apiKey();
        $this->withToken($noOrigins)->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall'])->assertUnprocessable()->assertJsonPath('reason', 'no_origins');
    }

    public function test_the_page_opens_only_inside_the_allowed_origins(): void
    {
        $page = $this->get($this->link(['from' => 'node:G-C01', 'to' => ['unit:1-205', 'facility:Prayer room'], 'step_free' => true, 'lang' => 'bn']))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors http://127.0.0.1:8000")
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeaderMissing('X-Frame-Options')
            ->assertSee('<html lang="bn">', false)
            ->assertDontSee($this->plain);

        $config = $this->config($page);
        $this->assertSame('node:G-C01', $config['from']);
        $this->assertSame(['unit:1-205', 'facility:Prayer room'], $config['to']);
        $this->assertTrue($config['stepFree']);
        $this->assertSame(['http://127.0.0.1:8000'], $config['origins']);
        $this->assertNotEmpty($config['token']);
    }

    public function test_a_tampered_or_expired_link_is_refused(): void
    {
        $url = $this->link(['to' => 'unit:1-205']);

        $this->get(str_replace('unit%3A1-205', 'unit%3AG-101', $url))->assertForbidden();
        $this->travel(16)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_a_revoked_key_or_an_unpublished_map_closes_the_page(): void
    {
        $url = $this->link();
        app(MapPublisher::class)->unpublish($this->map);
        $this->get($url)->assertNotFound()->assertSee('This map is not available right now.');

        app(MapPublisher::class)->publish($this->map);
        app(ApiKeyIssuer::class)->revoke($this->key);
        $this->get($url)->assertForbidden()->assertSee('This map link is no longer valid.');
    }

    public function test_the_page_reads_its_map_and_routes_with_its_session_token(): void
    {
        $token = $this->config($this->get($this->link()))['token'];

        $data = $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $this->map))->assertOk();
        $data->assertJsonPath('data.map.mall_ref', 'demo-mall')
            ->assertJsonPath('data.floors.0.floor_code', 'G')
            ->assertJsonPath('data.labels.E-G-101', 'G-101')
            ->assertJsonPath('data.labels.G-F-Toilets-G', 'Toilets G')
            ->assertJsonCount(19, 'data.nodes');
        $this->assertStringContainsString('signature=', $data->json('data.floors.0.plan.url'));
        $this->assertCount(18, $data->json('data.edges'));

        $slug = Checkpoint::query()->where('checkpoint_code', 'G-C01')->value('public_slug');
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.route', $this->map).'?'.http_build_query(['from' => "checkpoint:{$slug}", 'to' => ['unit:1-205']]))
            ->assertOk()->assertJsonPath('data.total.seconds', 29);
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.route', $this->map).'?from=node:G-C01&to=unit:Z-1')
            ->assertUnprocessable()->assertJsonPath('reason', 'unknown_place');
    }

    public function test_the_session_token_is_refused_when_missing_forged_for_another_map_revoked_or_old(): void
    {
        $token = $this->config($this->get($this->link()))['token'];
        $other = $this->pilotMap('other-mall');

        $this->getJson(route('embed.data', $this->map))->assertUnauthorized()->assertJsonPath('reason', 'session');
        $this->withHeader('X-Embed-Token', 'forged')->getJson(route('embed.data', $this->map))->assertUnauthorized();
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $other))->assertUnauthorized();
        $this->withHeader('X-Embed-Token', $token)->getJson('/embed/maps/no-such-map/data')->assertUnauthorized();

        $this->travel(241)->minutes();
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $this->map))->assertUnauthorized();
        $this->travelBack();

        app(ApiKeyIssuer::class)->revoke($this->key);
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $this->map))->assertUnauthorized();
    }

    public function test_administrators_set_the_origins_of_a_key(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.api-keys.origins', $this->key), ['allowed_origins' => "https://mall.example\nhttp://localhost:8000"])->assertRedirect();
        $this->assertSame(['https://mall.example', 'http://localhost:8000'], $this->key->refresh()->origins());

        $this->patch(route('admin.api-keys.origins', $this->key), ['allowed_origins' => 'not an origin'])->assertSessionHasErrors('allowed_origins');
        $this->post(route('admin.api-keys.store'), ['name' => 'Kiosk', 'abilities' => ['maps:read'], 'allowed_origins' => 'https://kiosk.example'])->assertRedirect();
        $this->assertSame(['https://kiosk.example'], ApiKey::query()->where('name', 'Kiosk')->sole()->origins());

        auth()->logout();
        $this->patch(route('admin.api-keys.origins', $this->key), ['allowed_origins' => 'https://evil.example'])->assertRedirect(route('login'));
    }

    public function test_the_create_key_command_takes_origins(): void
    {
        $this->artisan('api-key:create', ['name' => 'Smart Mall app 2', '--origin' => ['http://127.0.0.1:8000']])
            ->expectsOutputToContain('Embedded map allowed on: http://127.0.0.1:8000')
            ->assertSuccessful();
    }

    public function test_the_api_documentation_is_public_and_shows_no_keys(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('ISM Map Service API')
            ->assertSee('/embed-links')
            ->assertSee('ism-map:navigate')
            ->assertSee('facility-type:toilet')
            ->assertDontSee($this->key->prefix);
    }
}
