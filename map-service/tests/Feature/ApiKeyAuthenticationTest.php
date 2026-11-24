<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\MallMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_request_without_a_key_is_refused(): void
    {
        $this->getJson('/api/v1/maps')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Send an API key: Authorization: Bearer <key> or X-Api-Key: <key>.']);
    }

    public function test_an_unknown_key_is_refused(): void
    {
        $this->withToken('ism_'.str_repeat('x', 40))->getJson('/api/v1/maps')->assertUnauthorized();
        $this->withToken('not-a-key')->getJson('/api/v1/maps')->assertUnauthorized();
    }

    public function test_a_valid_key_works_as_bearer_token_and_as_x_api_key_header(): void
    {
        [, $plain] = $this->apiKey();

        $this->withToken($plain)->getJson('/api/v1/maps')->assertOk();
        $this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/maps')->assertOk();
    }

    public function test_revoked_and_expired_keys_are_refused(): void
    {
        [$revoked, $revokedPlain] = $this->apiKey();
        $revoked->forceFill(['revoked_at' => now()])->save();
        [$expired, $expiredPlain] = $this->apiKey();
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withToken($revokedPlain)->getJson('/api/v1/maps')->assertUnauthorized();
        $this->withToken($expiredPlain)->getJson('/api/v1/maps')->assertUnauthorized();
    }

    public function test_a_read_only_key_cannot_write(): void
    {
        $map = MallMap::factory()->create();
        [, $plain] = $this->apiKey(['maps:read']);

        $this->withToken($plain)->postJson("/api/v1/maps/{$map->id}/annotations", $this->annotationPayload())
            ->assertForbidden()
            ->assertJson(['message' => 'This API key may not do this (needs annotations:write).']);
        $this->assertDatabaseCount('annotations', 0);
    }

    public function test_the_key_is_checked_before_the_map_is_looked_up(): void
    {
        [, $reader] = $this->apiKey(['maps:read']);
        $missing = '/api/v1/maps/00000000-0000-0000-0000-000000000000';

        $this->getJson($missing)->assertUnauthorized();
        $this->withToken($reader)->postJson($missing.'/annotations', $this->annotationPayload())->assertForbidden();
        $this->withToken($reader)->getJson($missing)->assertNotFound();
    }

    public function test_only_a_hash_of_the_key_is_stored_and_use_is_recorded(): void
    {
        [$key, $plain] = $this->apiKey();

        $this->assertSame(hash('sha256', $plain), $key->key_hash);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $plain]);
        $this->assertStringStartsWith($key->prefix, $plain);
        $this->assertNull($key->last_used_at);

        $this->withToken($plain)->getJson('/api/v1/maps')->assertOk();

        $this->assertNotNull($key->fresh()->last_used_at);
    }

    public function test_each_key_is_rate_limited(): void
    {
        config(['mapservice.api_per_minute' => 2]);
        [, $plain] = $this->apiKey();

        $this->withToken($plain)->getJson('/api/v1/maps')->assertOk();
        $this->withToken($plain)->getJson('/api/v1/maps')->assertOk();
        $this->withToken($plain)->getJson('/api/v1/maps')->assertTooManyRequests();
    }

    public function test_the_create_command_prints_a_working_key_once(): void
    {
        $this->assertSame(0, Artisan::call('api-key:create', ['name' => 'Smart Mall app', '--ability' => ['maps:read']]));
        preg_match('/ism_[A-Za-z0-9]{40}/', Artisan::output(), $match);

        $this->assertNotEmpty($match);
        $this->assertSame(['maps:read'], ApiKey::query()->sole()->abilities);
        $this->withToken($match[0])->getJson('/api/v1/maps')->assertOk();
    }

    public function test_the_create_command_refuses_unknown_abilities(): void
    {
        $this->assertSame(1, Artisan::call('api-key:create', ['name' => 'Bad', '--ability' => ['everything']]));
        $this->assertDatabaseCount('api_keys', 0);
    }
}
