<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\MallMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MapApiTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        [, $this->key] = $this->apiKey();
    }

    public function test_the_list_shows_published_maps_only_with_their_counts(): void
    {
        $published = MallMap::factory()->create(['name' => 'Ground floor map']);
        Annotation::factory()->count(2)->for($published)->create();
        MallMap::factory()->draft()->create(['name' => 'Work in progress']);

        $this->withToken($this->key)->getJson('/api/v1/maps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.name', 'Ground floor map')
            ->assertJsonPath('data.0.floor_codes', ['G'])
            ->assertJsonPath('data.0.annotations_count', 2);
    }

    public function test_a_map_returns_its_package_with_signed_file_links(): void
    {
        $map = MallMap::factory()->create();

        $response = $this->withToken($this->key)->getJson("/api/v1/maps/{$map->id}")
            ->assertOk()
            ->assertJsonPath('format', 'ism-map-package')
            ->assertJsonPath('nodes.0.node_code', 'G-N0001')
            ->assertJsonPath('map.id', $map->id);

        $modelUrl = $response->json('floors.0.model.url');
        $this->assertSame($modelUrl, $response->json('file_urls')['floors/G_model.glb']);
        $this->assertStringContainsString('signature=', $modelUrl);
        $this->assertNotNull($response->json('urls_expire_at'));

        $this->get($modelUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'model/gltf-binary')
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_a_draft_or_missing_map_is_not_found(): void
    {
        $draft = MallMap::factory()->draft()->create();

        $this->withToken($this->key)->getJson("/api/v1/maps/{$draft->id}")->assertNotFound();
        $this->withToken($this->key)->getJson('/api/v1/maps/00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->withToken($this->key)->getJson("/api/v1/maps/{$draft->id}/annotations")->assertNotFound();
    }

    public function test_file_links_need_a_valid_signature_and_a_package_file(): void
    {
        $map = MallMap::factory()->create();
        Storage::disk('local')->put($map->storage_dir.'/secret.txt', 'not part of the package');

        $this->get("/files/maps/{$map->id}/floors/G_model.glb")->assertForbidden();

        $tampered = URL::temporarySignedRoute('files.show', now()->addHour(), ['mallMap' => $map->id, 'path' => 'floors/G_model.glb']);
        $this->get(str_replace('G_model', 'G_plan', $tampered))->assertForbidden();

        $expired = URL::temporarySignedRoute('files.show', now()->subMinute(), ['mallMap' => $map->id, 'path' => 'floors/G_model.glb']);
        $this->get($expired)->assertForbidden();

        $notInPackage = URL::temporarySignedRoute('files.show', now()->addHour(), ['mallMap' => $map->id, 'path' => 'secret.txt']);
        $this->get($notInPackage)->assertNotFound();

        $traversal = URL::temporarySignedRoute('files.show', now()->addHour(), ['mallMap' => $map->id, 'path' => '../other/map_package.json']);
        $this->get($traversal)->assertNotFound();
    }
}
