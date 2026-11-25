<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\User;
use App\Services\MapPublisher;
use Database\Factories\MallMapFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ISM-61: QR checkpoint lifecycle and scan-to-start-node.
 */
class CheckpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function slugOf(string $code, string $mall = 'demo-mall'): string
    {
        return Checkpoint::query()->where('mall_ref', $mall)->where('checkpoint_code', $code)->value('public_slug');
    }

    public function test_publishing_a_map_creates_its_checkpoints_with_random_slugs(): void
    {
        $this->pilotMap();

        $checkpoints = Checkpoint::query()->orderBy('checkpoint_code')->get();
        $this->assertSame(['1-C01', 'G-C01'], $checkpoints->pluck('checkpoint_code')->all());
        foreach ($checkpoints as $checkpoint) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $checkpoint->public_slug);
            $this->assertTrue($checkpoint->isActive());
        }
        $this->assertSame('Main entrance, pillar facing the doors', $checkpoints[1]->label);
    }

    public function test_a_new_map_version_keeps_printed_codes_working(): void
    {
        $this->pilotMap();
        $slug = $this->slugOf('G-C01');

        $package = MallMapFactory::pilotPackage();
        $package['checkpoints'][0]['label'] = 'Main entrance, moved to the left pillar';
        $this->pilotMap(overrides: ['checkpoints' => $package['checkpoints']]);

        $this->assertSame(2, Checkpoint::query()->count());
        $this->assertSame($slug, $this->slugOf('G-C01'));
        $this->assertSame('Main entrance, moved to the left pillar', Checkpoint::query()->where('public_slug', $slug)->value('label'));
    }

    public function test_a_map_without_a_mall_or_a_draft_creates_no_checkpoints(): void
    {
        $this->pilotMap(null);
        $this->pilotMap('demo-mall', publish: false);

        $this->assertSame(0, Checkpoint::query()->count());
    }

    public function test_a_scan_resolves_to_the_start_node_on_the_published_map(): void
    {
        $map = $this->pilotMap();
        [, $key] = $this->apiKey();
        $slug = $this->slugOf('1-C01');

        $this->withToken($key)->getJson("/api/v1/checkpoints/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.checkpoint_code', '1-C01')
            ->assertJsonPath('data.mall_ref', 'demo-mall')
            ->assertJsonPath('data.map_id', $map->id)
            ->assertJsonPath('data.floor_code', '1')
            ->assertJsonPath('data.node.node_code', '1-C01')
            ->assertJsonPath('data.node.position.x', 5)
            ->assertJsonPath('data.qr.yaw_deg', 270)
            ->assertJsonPath('data.start', "checkpoint:{$slug}");
    }

    public function test_a_scan_needs_an_api_key(): void
    {
        $this->pilotMap();

        $this->getJson('/api/v1/checkpoints/'.$this->slugOf('G-C01'))->assertUnauthorized();
    }

    public function test_unknown_retired_and_missing_codes_are_refused_with_a_reason(): void
    {
        $map = $this->pilotMap();
        [, $key] = $this->apiKey();
        $slug = $this->slugOf('G-C01');

        $this->withToken($key)->getJson('/api/v1/checkpoints/nosuchcode12')->assertNotFound()->assertJsonPath('reason', 'unknown');

        Checkpoint::query()->where('public_slug', $slug)->update(['status' => 'retired', 'retired_at' => now()]);
        $this->withToken($key)->getJson("/api/v1/checkpoints/{$slug}")->assertStatus(410)->assertJsonPath('reason', 'retired');
        Checkpoint::query()->where('public_slug', $slug)->update(['status' => 'active', 'retired_at' => null]);

        $package = MallMapFactory::pilotPackage();
        $this->pilotMap(overrides: ['checkpoints' => [$package['checkpoints'][1]]]);
        $this->withToken($key)->getJson("/api/v1/checkpoints/{$slug}")->assertStatus(409)->assertJsonPath('reason', 'not_on_map');

        app(MapPublisher::class)->publish($map);
        $this->withToken($key)->getJson("/api/v1/checkpoints/{$slug}")->assertOk();

        app(MapPublisher::class)->unpublish($map->refresh());
        $this->withToken($key)->getJson("/api/v1/checkpoints/{$slug}")->assertNotFound()->assertJsonPath('reason', 'no_map');
    }

    public function test_the_admin_page_lists_codes_with_the_address_printed_in_them(): void
    {
        config(['mapservice.qr_url' => 'https://mall.example/malls/{mall}/map/qr/{slug}']);
        $this->pilotMap();
        $slug = $this->slugOf('G-C01');

        $this->actingAs(User::factory()->create())->get(route('admin.checkpoints.index'))
            ->assertOk()
            ->assertSee('G-C01')
            ->assertSee("https://mall.example/malls/demo-mall/map/qr/{$slug}")
            ->assertSee('on the published map, floor G');
    }

    public function test_administrators_retire_reactivate_and_replace_a_code(): void
    {
        $this->pilotMap();
        $admin = User::factory()->create();
        $checkpoint = Checkpoint::query()->where('checkpoint_code', 'G-C01')->sole();
        $oldSlug = $checkpoint->public_slug;
        [, $key] = $this->apiKey();

        $this->actingAs($admin)->post(route('admin.checkpoints.retire', $checkpoint))->assertRedirect();
        $this->assertSame('retired', $checkpoint->refresh()->status);
        $this->assertNotNull($checkpoint->retired_at);

        $this->post(route('admin.checkpoints.reactivate', $checkpoint))->assertRedirect();
        $this->assertTrue($checkpoint->refresh()->isActive());

        $this->post(route('admin.checkpoints.replace', $checkpoint))->assertRedirect();
        $newSlug = $checkpoint->refresh()->public_slug;
        $this->assertNotSame($oldSlug, $newSlug);
        $this->withToken($key)->getJson("/api/v1/checkpoints/{$oldSlug}")->assertNotFound();
        $this->withToken($key)->getJson("/api/v1/checkpoints/{$newSlug}")->assertOk();
    }

    public function test_guests_cannot_manage_codes(): void
    {
        $checkpoint = Checkpoint::factory()->create();

        $this->get(route('admin.checkpoints.index'))->assertRedirect(route('login'));
        $this->post(route('admin.checkpoints.retire', $checkpoint))->assertRedirect(route('login'));
        $this->post(route('admin.checkpoints.replace', $checkpoint))->assertRedirect(route('login'));
        $this->assertTrue($checkpoint->refresh()->isActive());
    }
}
