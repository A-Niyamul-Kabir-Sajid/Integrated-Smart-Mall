<?php

namespace Tests\Feature;

use App\Exceptions\RouteNotPossible;
use App\Models\MallMap;
use App\Models\MapEndPoint;
use App\Models\User;
use App\Services\ApiKeyIssuer;
use App\Services\EdgeStatusService;
use App\Services\EndPointService;
use App\Services\Routing\RoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * ISM-114: administrators add end points to a map; each gets a key, is joined
 * to the nearest walkway and can be routed to as point:<key>.
 */
class EndPointTest extends TestCase
{
    use RefreshDatabase;

    private MallMap $map;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->map = $this->pilotMap();
    }

    private function endPoints(): EndPointService
    {
        return app(EndPointService::class);
    }

    /**
     * @param  list<string>|string  $to
     * @return array<string, mixed>
     */
    private function route(string $from, array|string $to, ?MallMap $map = null): array
    {
        return app(RoutePlanner::class)->plan($map ?? $this->map, $from, (array) $to);
    }

    public function test_an_end_point_gets_a_key_and_joins_the_nearest_walkway_through_a_junction(): void
    {
        $baseEdges = $this->map->edges()->count();

        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $point->key);
        $this->assertTrue($point->isLinked());
        $this->assertSame(1.5, $point->link_m);
        $this->assertSame("point:{$point->key}", $point->place());
        $junction = DB::table('map_nodes')->where('node_code', "P-{$point->key}-J")->first();
        $this->assertEquals([15.0, 0.0], [(float) $junction->x_m, (float) $junction->y_m]);
        $this->assertSame('endpoint', DB::table('map_nodes')->where('id', $point->map_node_id)->value('node_type'));
        // Two directions of the walkway split in halves, plus the link both ways.
        $this->assertSame($baseEdges + 6, $this->map->edges()->count());
        $this->assertSame(4, DB::table('map_edges')->where('map_end_point_id', $point->id)->whereNotNull('parent_edge_id')->count());
    }

    public function test_a_point_near_a_node_joins_that_node_without_a_junction(): void
    {
        $point = $this->endPoints()->create($this->map, 'By the corner', 'G', 10.1, 0.1);

        $this->assertNull(DB::table('map_nodes')->where('node_code', "P-{$point->key}-J")->first());
        $this->assertSame(2, DB::table('map_edges')->where('map_end_point_id', $point->id)->count());
        $this->assertSame(['G-C01', 'G-N0001', "P-{$point->key}"], array_column($this->route('node:G-C01', $point->place())['path'], 'node_code'));
    }

    public function test_routes_reach_an_end_point_by_its_key_and_name_it(): void
    {
        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);

        $route = $this->route('node:G-C01', $point->place());

        $this->assertSame(['G-C01', 'G-N0001', "P-{$point->key}-J", "P-{$point->key}"], array_column($route['path'], 'node_code'));
        $this->assertSame(16.5, $route['total']['length_m']);
        $this->assertSame('Arrive at Kiosk.', end($route['steps'])['instruction']);
        $this->assertSame('E-1-205', $this->route($point->place(), 'unit:1-205')['to'][0]['node_code']);
    }

    public function test_closing_the_walkway_closes_the_halves_the_end_point_added(): void
    {
        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);
        app(EdgeStatusService::class)->set($this->map, 'G-N0001', 'G-N0002', 'closed');

        try {
            $this->route('node:G-C01', $point->place());
            $this->fail('The closed walkway was used through its halves.');
        } catch (RouteNotPossible $error) {
            $this->assertSame('unreachable', $error->reason);
        }

        app(EdgeStatusService::class)->set($this->map, 'G-N0001', 'G-N0002', 'open');
        $this->assertSame(16.5, $this->route('node:G-C01', $point->place())['total']['length_m']);
    }

    public function test_a_point_far_from_every_walkway_or_on_an_unknown_floor_is_refused(): void
    {
        foreach ([['G', 40.0, 40.0, 'No walkway of floor G is within 6 m'], ['9', 1.0, 1.0, 'This map has no floor 9']] as [$floor, $x, $y, $message]) {
            try {
                $this->endPoints()->create($this->map, 'Nowhere', $floor, $x, $y);
                $this->fail('Expected a refusal.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
        $this->assertSame(0, MapEndPoint::query()->count());
        $this->assertSame(0, DB::table('map_nodes')->whereNotNull('map_end_point_id')->count());
    }

    public function test_deleting_an_end_point_leaves_the_map_s_own_graph_as_it_was(): void
    {
        $nodes = $this->map->nodes()->count();
        $edges = $this->map->edges()->count();
        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);
        $other = $this->endPoints()->create($this->map, 'Bench', 'G', 15.5, -1.0);

        $this->endPoints()->delete($point);

        $this->assertSame($nodes + 2, $this->map->nodes()->count());
        $this->assertSame($edges + 6, $this->map->edges()->count());
        $this->assertSame(['G-C01', 'G-N0001', "P-{$other->key}-J", "P-{$other->key}"], array_column($this->route('node:G-C01', $other->place())['path'], 'node_code'));

        $this->endPoints()->delete($other);
        $this->assertSame($nodes, $this->map->nodes()->count());
        $this->assertSame($edges, $this->map->edges()->count());
    }

    public function test_rebuilding_the_graph_joins_end_points_again(): void
    {
        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);

        $this->artisan('map:rebuild-graph', ['map' => $this->map->id])->assertSuccessful();

        $this->assertTrue($point->refresh()->isLinked());
        $this->assertSame(16.5, $this->route('node:G-C01', $point->place())['total']['length_m']);
    }

    public function test_a_new_map_version_of_the_mall_keeps_the_end_points_and_their_keys(): void
    {
        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);

        $version = $this->pilotMap('demo-mall');

        $copy = MapEndPoint::query()->where('mall_map_id', $version->id)->sole();
        $this->assertSame($point->key, $copy->key);
        $this->assertTrue($copy->isLinked());
        $this->assertSame('Kiosk', $this->route('node:G-C01', $point->place(), $version)['to'][0]['label']);
        $this->assertSame(0, MapEndPoint::query()->where('mall_map_id', $this->pilotMap('other-mall')->id)->count());
    }

    public function test_the_api_lists_end_points_routes_to_them_and_the_embed_labels_them(): void
    {
        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);
        [, $key] = app(ApiKeyIssuer::class)->issue('App', ['maps:read'], null, ['http://127.0.0.1:8000']);

        $this->withToken($key)->getJson('/api/v1/malls/demo-mall')
            ->assertOk()
            ->assertJsonPath('data.places.end_points.0', ['key' => $point->key, 'name' => 'Kiosk', 'floor_code' => 'G', 'place' => $point->place()]);
        $this->withToken($key)->getJson('/api/v1/malls/demo-mall/routes?'.http_build_query(['from' => 'node:G-C01', 'to' => $point->place()]))
            ->assertOk()->assertJsonPath('data.to.0.label', 'Kiosk');
        $url = $this->withToken($key)->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall', 'to' => $point->place()])->assertCreated()->json('data.url');

        preg_match('#id="embed-config">(.*?)</script>#s', $this->get($url)->assertOk()->getContent(), $match);
        $token = json_decode(html_entity_decode($match[1]), true)['token'];
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $this->map))
            ->assertOk()->assertJsonPath("data.labels.P-{$point->key}", 'Kiosk');
    }

    public function test_administrators_add_rename_and_delete_end_points_and_see_their_keys(): void
    {
        $this->actingAs(User::factory()->create());

        $created = $this->postJson(route('admin.maps.end-points.store', $this->map), ['name' => 'Kiosk', 'floor_code' => 'G', 'x' => 15, 'y' => 1.5])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kiosk')
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.joined_at', ['x' => 15, 'y' => 0, 'z' => 0])
            ->assertJsonPath('data.app_url', 'http://127.0.0.1:8000/malls/demo-mall/map?to=point%3A'.MapEndPoint::query()->sole()->key);
        $point = MapEndPoint::query()->sole();
        $this->assertSame($point->key, $created->json('data.key'));

        $this->get(route('admin.maps.end-points', $this->map))->assertOk()->assertSee($point->key)->assertSee('on the map');
        $this->getJson(route('admin.maps.end-points', $this->map))->assertOk()->assertJsonPath('data.0.key', $point->key);

        $this->post(route('admin.maps.end-points.store', $this->map), ['name' => 'Far', 'floor_code' => 'G', 'x' => 40, 'y' => 40])->assertSessionHasErrors('x');
        $this->post(route('admin.maps.end-points.store', $this->map), ['name' => '', 'floor_code' => 'G', 'x' => 'a', 'y' => 1])->assertSessionHasErrors(['name', 'x']);

        $this->patch(route('admin.maps.end-points.update', [$this->map, $point]), ['name' => 'Info kiosk'])->assertRedirect();
        $this->assertSame('Info kiosk', $point->refresh()->name);

        $other = $this->pilotMap('other-mall');
        $this->delete(route('admin.maps.end-points.destroy', [$other, $point]))->assertNotFound();

        $this->deleteJson(route('admin.maps.end-points.destroy', [$this->map, $point]))->assertNoContent();
        $this->assertSame(0, MapEndPoint::query()->count());
    }

    public function test_guests_cannot_see_or_change_end_points(): void
    {
        $point = $this->endPoints()->create($this->map, 'Kiosk', 'G', 15.0, 1.5);

        $this->get(route('admin.maps.end-points', $this->map))->assertRedirect(route('login'));
        $this->post(route('admin.maps.end-points.store', $this->map), ['name' => 'X', 'floor_code' => 'G', 'x' => 15, 'y' => 1])->assertRedirect(route('login'));
        $this->delete(route('admin.maps.end-points.destroy', [$this->map, $point]))->assertRedirect(route('login'));
        $this->assertSame(1, MapEndPoint::query()->count());
    }
}
