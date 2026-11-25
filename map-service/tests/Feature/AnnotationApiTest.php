<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\MallMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnotationApiTest extends TestCase
{
    use RefreshDatabase;

    private MallMap $map;

    private string $writer;

    private string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->map = MallMap::factory()->create();
        [, $this->writer] = $this->apiKey(['maps:read', 'annotations:write']);
        [, $this->reader] = $this->apiKey(['maps:read']);
    }

    public function test_annotations_are_listed_and_filtered(): void
    {
        Annotation::factory()->for($this->map)->create(['code' => 'ann-0001', 'unit_code' => 'G-101', 'text' => 'Cafe Aroma']);
        Annotation::factory()->for($this->map)->advertisement()->create(['code' => 'ann-0002', 'text' => 'Winter Sale']);

        $this->withToken($this->reader)->getJson("/api/v1/maps/{$this->map->id}/annotations")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'ann-0001')
            ->assertJsonPath('data.0.position', ['x' => 1.5, 'y' => 2, 'z' => 1.4]);

        $this->withToken($this->reader)->getJson("/api/v1/maps/{$this->map->id}/annotations?unit_code=G-101")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.text', 'Cafe Aroma');
        $this->withToken($this->reader)->getJson("/api/v1/maps/{$this->map->id}/annotations?kind=advertisement")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.text', 'Winter Sale');
        $this->withToken($this->reader)->getJson("/api/v1/maps/{$this->map->id}/annotations/ann-0002")
            ->assertOk()->assertJsonPath('data.kind', 'advertisement');
        $this->withToken($this->reader)->getJson("/api/v1/maps/{$this->map->id}/annotations/ann-9999")->assertNotFound();
    }

    public function test_a_writer_creates_an_annotation_with_the_next_code(): void
    {
        $this->withToken($this->writer)->postJson("/api/v1/maps/{$this->map->id}/annotations", $this->annotationPayload(['id' => null]))
            ->assertCreated()
            ->assertJsonPath('data.id', 'ann-0001')
            ->assertJsonPath('data.text', 'Cafe Aroma')
            ->assertJsonPath('data.yaw_deg', 180);

        $this->withToken($this->writer)->postJson("/api/v1/maps/{$this->map->id}/annotations", $this->annotationPayload(['text' => 'Book Nook']))
            ->assertCreated()
            ->assertJsonPath('data.id', 'ann-0002');
    }

    public function test_invalid_annotations_are_refused(): void
    {
        $url = "/api/v1/maps/{$this->map->id}/annotations";

        $this->withToken($this->writer)->postJson($url, $this->annotationPayload(['background' => 'red', 'width_m' => 50, 'kind' => 'banner', 'yaw_deg' => 360]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['background', 'width_m', 'kind', 'yaw_deg']);
        $this->withToken($this->writer)->postJson($url, $this->annotationPayload(['floor_code' => '9']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['floor_code']);
        $this->withToken($this->writer)->postJson($url, $this->annotationPayload(['image' => 'data:image/gif;base64,R0lGODlhAQABAAAAACw=']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
        $this->withToken($this->writer)->postJson($url, $this->annotationPayload(['image' => 'data:image/png;base64,bm90LWEtcG5n']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertDatabaseCount('annotations', 0);
    }

    public function test_an_image_is_stored_served_kept_and_deleted(): void
    {
        $created = $this->withToken($this->writer)->postJson("/api/v1/maps/{$this->map->id}/annotations", $this->annotationPayload(['image' => self::PNG_DATA_URL]))
            ->assertCreated();
        $imageUrl = $created->json('data.image');
        $annotation = Annotation::query()->sole();
        Storage::disk('local')->assertExists($annotation->image_path);
        $this->get($imageUrl)->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->withToken($this->writer)->putJson("/api/v1/maps/{$this->map->id}/annotations/ann-0001", $this->annotationPayload(['text' => 'Cafe Aroma 2', 'image' => $imageUrl]))
            ->assertOk()
            ->assertJsonPath('data.text', 'Cafe Aroma 2');
        $this->assertSame($annotation->image_path, $annotation->fresh()->image_path);

        $this->withToken($this->writer)->deleteJson("/api/v1/maps/{$this->map->id}/annotations/ann-0001")->assertNoContent();
        $this->assertDatabaseCount('annotations', 0);
        Storage::disk('local')->assertMissing($annotation->image_path);
    }

    public function test_drafts_are_hidden_from_writers_too(): void
    {
        $draft = MallMap::factory()->draft()->create();

        $this->withToken($this->writer)->postJson("/api/v1/maps/{$draft->id}/annotations", $this->annotationPayload())->assertNotFound();
    }
}
