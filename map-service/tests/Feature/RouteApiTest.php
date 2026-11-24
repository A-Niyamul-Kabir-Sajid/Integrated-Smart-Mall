<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ISM-62 over HTTP: routes, places and closures through /api/v1, and the
 * administrators' closures page.
 */
class RouteApiTest extends TestCase
{
    use RefreshDatabase;

    private MallMap $map;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->map = $this->pilotMap();
        [, $this->key] = $this->apiKey();
    }

    public function test_a_route_is_answered_by_mall_with_get_and_by_map_with_post(): void
    {
        $slug = Checkpoint::query()->where('checkpoint_code', 'G-C01')->value('public_slug');

        $this->withToken($this->key)->getJson('/api/v1/malls/demo-mall/routes?'.http_build_query(['from' => "checkpoint:{$slug}", 'to' => 'unit:1-205']))
            ->assertOk()
            ->assertJsonPath('data.map_id', $this->map->id)
            ->assertJsonPath('data.total.seconds', 29)
            ->assertJsonPath('data.to.0.node_code', 'E-1-205')
            ->assertJsonPath('data.steps.0.type', 'start');

        $this->withToken($this->key)->postJson("/api/v1/maps/{$this->map->id}/routes", ['from' => 'node:G-C01', 'to' => ['unit:1-205'], 'step_free' => true, 'cost' => 'distance'])
            ->assertOk()
            ->assertJsonPath('data.step_free', true)
            ->assertJsonPath('data.cost', 'distance')
            ->assertJsonPath('data.total.length_m', 37);
    }

    public function test_bad_route_questions_get_422_with_a_reason(): void
    {
        $this->withToken($this->key)->postJson('/api/v1/malls/demo-mall/routes', ['to' => 'unit:G-101'])
            ->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->withToken($this->key)->postJson('/api/v1/malls/demo-mall/routes', ['from' => 'node:G-C01', 'to' => 'unit:G-999'])
            ->assertUnprocessable()->assertJsonPath('reason', 'unknown_place')->assertJsonValidationErrors('to');
        $this->withToken($this->key)->postJson('/api/v1/malls/demo-mall/routes', ['from' => 'node:G-C01', 'to' => 'unit:G-101', 'cost' => 'cheapest'])
            ->assertUnprocessable()->assertJsonValidationErrors('cost');
        $this->withToken($this->key)->postJson('/api/v1/malls/demo-mall/routes', ['from' => 'node:G-C01', 'to' => array_fill(0, 11, 'unit:G-101')])
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_routes_need_a_key_and_a_published_map(): void
    {
        $this->getJson('/api/v1/malls/demo-mall/routes?from=node:G-C01&to=unit:G-101')->assertUnauthorized();
        $this->withToken($this->key)->getJson('/api/v1/malls/no-such-mall/routes?from=node:G-C01&to=unit:G-101')->assertNotFound();

        $draft = $this->pilotMap('draft-mall', publish: false);
        $this->withToken($this->key)->getJson("/api/v1/maps/{$draft->id}/routes?from=node:G-C01&to=unit:G-101")->assertNotFound();
        $this->withToken($this->key)->getJson('/api/v1/malls/draft-mall')->assertNotFound();
    }

    public function test_the_mall_endpoint_lists_its_map_and_places(): void
    {
        $slug = Checkpoint::query()->where('checkpoint_code', '1-C01')->value('public_slug');

        $this->withToken($this->key)->getJson('/api/v1/malls/demo-mall')
            ->assertOk()
            ->assertJsonPath('data.id', $this->map->id)
            ->assertJsonPath('data.mall_ref', 'demo-mall')
            ->assertJsonPath('data.places.floors.1.name', 'First floor')
            ->assertJsonPath('data.places.units.0', ['unit_code' => '1-205', 'floor_code' => '1', 'entrances' => 1, 'place' => 'unit:1-205'])
            ->assertJsonPath('data.places.units.1.entrances', 2)
            ->assertJsonPath('data.places.facility_types.1', ['type' => 'toilet', 'count' => 2, 'place' => 'facility-type:toilet'])
            ->assertJsonPath('data.places.checkpoints.0.place', "checkpoint:{$slug}");
    }

    public function test_closures_through_the_api_need_graph_write_and_change_routes(): void
    {
        $payload = ['from' => 'G-X-East-Escalator', 'to' => '1-X-East-Escalator', 'status' => 'closed', 'reason' => 'Repairs', 'both_ways' => false];
        $this->withToken($this->key)->patchJson("/api/v1/maps/{$this->map->id}/edges", $payload)->assertForbidden();

        [, $writer] = $this->apiKey(['maps:read', 'graph:write']);
        $this->withToken($writer)->patchJson("/api/v1/maps/{$this->map->id}/edges", $payload)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.connector', 'East Escalator')
            ->assertJsonPath('data.0.reason', 'Repairs');

        $this->withToken($this->key)->getJson("/api/v1/maps/{$this->map->id}/closures")->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($this->key)->getJson('/api/v1/malls/demo-mall/routes?from=node:G-C01&to=unit:1-205')
            ->assertOk()->assertJsonPath('data.total.seconds', 47.5);

        $this->withToken($writer)->patchJson("/api/v1/maps/{$this->map->id}/edges", ['from' => 'G-C01', 'to' => 'E-1-205', 'status' => 'closed'])
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_administrators_close_and_reopen_on_the_closures_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.maps.closures', $this->map))->assertOk()->assertSee('Nothing is closed.')->assertSee('G-X-Central-Lift');

        $this->post(route('admin.maps.closures.update', $this->map), ['from' => 'G-X-Central-Lift', 'to' => '1-X-Central-Lift', 'status' => 'closed', 'reason' => 'Lift maintenance', 'both_ways' => '1'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame(2, $this->map->edges()->where('status', 'closed')->count());
        $this->get(route('admin.maps.closures', $this->map))->assertSee('Lift maintenance');

        $this->post(route('admin.maps.closures.update', $this->map), ['from' => 'G-X-Central-Lift', 'to' => '1-X-Central-Lift', 'status' => 'open', 'both_ways' => '0']);
        $this->assertSame(1, $this->map->edges()->where('status', 'closed')->count());

        $this->post(route('admin.maps.closures.update', $this->map), ['from' => 'G-C01', 'to' => 'nowhere', 'status' => 'closed'])->assertSessionHasErrors('to');
    }

    public function test_guests_cannot_see_or_change_closures(): void
    {
        $this->get(route('admin.maps.closures', $this->map))->assertRedirect(route('login'));
        $this->post(route('admin.maps.closures.update', $this->map), ['from' => 'G-C01', 'to' => 'G-N0001', 'status' => 'closed'])->assertRedirect(route('login'));
        $this->assertSame(0, $this->map->edges()->where('status', 'closed')->count());
    }
}
