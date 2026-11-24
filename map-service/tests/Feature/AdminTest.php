<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\ApiKey;
use App\Models\MallMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['email' => 'admin@map.test']);
    }

    public function test_guests_are_sent_to_sign_in_and_json_gets_401(): void
    {
        $map = MallMap::factory()->create();

        $this->get('/admin/maps')->assertRedirect('/login');
        $this->get('/admin/api-keys')->assertRedirect('/login');
        $this->getJson("/admin/maps/{$map->id}/package")->assertUnauthorized();
        $this->putJson("/admin/maps/{$map->id}/annotations", ['annotations' => []])->assertUnauthorized();
    }

    public function test_sign_in_with_the_right_password_only(): void
    {
        $this->post('/login', ['email' => 'admin@map.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'admin@map.test', 'password' => 'password'])->assertRedirect('/admin/maps');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_the_maps_page_lists_packages_and_imports_one(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ism-root-'.uniqid();
        $this->packageFolder(root: $root);
        config(['mapservice.import_root' => $root]);

        $this->actingAs($this->admin)->get('/admin/maps')->assertOk()->assertSee('sample/package');

        $this->actingAs($this->admin)->post('/admin/maps/import', ['folder' => 'sample/package', 'name' => 'Sample floor', 'publish' => '1'])
            ->assertRedirect('/admin/maps')
            ->assertSessionHas('status', 'Imported “Sample floor”.');
        $this->assertTrue(MallMap::query()->sole()->isPublished());

        $this->actingAs($this->admin)->post('/admin/maps/import', ['folder' => '../../', 'name' => 'Escape'])
            ->assertSessionHasErrors(['folder' => 'Choose a package folder from the list.']);
        $this->assertDatabaseCount('mall_maps', 1);
    }

    public function test_the_viewer_loads_a_map_and_saves_all_annotations(): void
    {
        $map = MallMap::factory()->draft()->create();
        Annotation::factory()->for($map)->create(['code' => 'ann-0001', 'text' => 'Old sign']);
        Annotation::factory()->for($map)->create(['code' => 'ann-0002', 'text' => 'Removed sign']);

        $this->actingAs($this->admin)->get("/admin/maps/{$map->id}")->assertRedirect("/viewer/index.html?map={$map->id}");
        $this->actingAs($this->admin)->getJson("/admin/maps/{$map->id}/package")->assertOk()->assertJsonStructure(['file_urls' => ['floors/G_model.glb']]);
        $this->actingAs($this->admin)->getJson("/admin/maps/{$map->id}/annotations")->assertOk()->assertJsonCount(2, 'annotations');

        $this->actingAs($this->admin)->putJson("/admin/maps/{$map->id}/annotations", ['annotations' => [
            $this->annotationPayload(['id' => 'ann-0001', 'text' => 'Cafe Aroma']),
            $this->annotationPayload(['id' => 'ann-0003', 'kind' => 'advertisement', 'width_m' => 0.8, 'mount' => 'floor', 'lift_m' => 0.1, 'text' => 'Winter Sale', 'image' => self::PNG_DATA_URL]),
        ]])
            ->assertOk()
            ->assertJsonPath('format', 'ism-map-annotations')
            ->assertJsonCount(2, 'annotations')
            ->assertJsonPath('annotations.0.text', 'Cafe Aroma')
            ->assertJsonPath('annotations.1.id', 'ann-0003');

        $this->assertSame(['ann-0001', 'ann-0003'], $map->annotations()->pluck('code')->all());
        $this->assertNotNull(Annotation::query()->where('code', 'ann-0003')->value('image_path'));
    }

    public function test_the_viewer_save_is_refused_when_invalid_and_changes_nothing(): void
    {
        $map = MallMap::factory()->create();
        Annotation::factory()->for($map)->create(['code' => 'ann-0001', 'text' => 'Keep me']);

        $this->actingAs($this->admin)->putJson("/admin/maps/{$map->id}/annotations", ['annotations' => [$this->annotationPayload(['text' => ''])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['annotations.0.text']);
        $this->actingAs($this->admin)->putJson("/admin/maps/{$map->id}/annotations", ['annotations' => [$this->annotationPayload(['floor_code' => 'X9'])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['annotations.0.floor_code']);

        $this->assertSame(['Keep me'], $map->annotations()->pluck('text')->all());
    }

    public function test_publish_unpublish_and_delete(): void
    {
        $map = MallMap::factory()->draft()->create();

        $this->actingAs($this->admin)->patch("/admin/maps/{$map->id}/status", ['status' => 'published'])->assertSessionHas('status');
        $this->assertTrue($map->fresh()->isPublished());
        $this->actingAs($this->admin)->patch("/admin/maps/{$map->id}/status", ['status' => 'archived'])->assertSessionHasErrors('status');

        $this->actingAs($this->admin)->delete("/admin/maps/{$map->id}")->assertRedirect('/admin/maps');
        $this->assertDatabaseCount('mall_maps', 0);
        Storage::disk('local')->assertMissing($map->storage_dir.'/map_package.json');
    }

    public function test_an_admin_creates_a_key_sees_it_once_and_revokes_it(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/api-keys', ['name' => 'Smart Mall app', 'abilities' => ['maps:read'], 'expires_in_days' => 30]);
        $plain = session('new_key');

        $response->assertRedirect('/admin/api-keys');
        $this->assertMatchesRegularExpression('/^ism_[A-Za-z0-9]{40}$/', $plain);
        $key = ApiKey::query()->sole();
        $this->assertTrue($key->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->withToken($plain)->getJson('/api/v1/maps')->assertOk();

        $this->actingAs($this->admin)->get('/admin/api-keys')->assertOk()->assertSee($plain);
        $this->actingAs($this->admin)->get('/admin/api-keys')->assertOk()->assertSee($key->prefix)->assertDontSee($plain);

        $this->actingAs($this->admin)->post("/admin/api-keys/{$key->id}/revoke")->assertSessionHas('status');
        $this->assertNotNull($key->fresh()->revoked_at);
        $this->withToken($plain)->getJson('/api/v1/maps')->assertUnauthorized();

        $this->actingAs($this->admin)->post('/admin/api-keys', ['name' => 'Bad', 'abilities' => ['admin:all']])->assertSessionHasErrors('abilities.0');
    }

    public function test_the_admin_command_creates_and_resets_an_administrator(): void
    {
        $this->assertSame(0, Artisan::call('map:admin', ['email' => 'second@map.test', '--password' => 'secret-pass']));
        $this->post('/login', ['email' => 'second@map.test', 'password' => 'secret-pass'])->assertRedirect('/admin/maps');

        $this->assertSame(1, Artisan::call('map:admin', ['email' => 'third@map.test', '--password' => 'short']));
        $this->assertSame(1, Artisan::call('map:admin', ['email' => 'not-an-email', '--password' => 'long-enough']));
    }
}
