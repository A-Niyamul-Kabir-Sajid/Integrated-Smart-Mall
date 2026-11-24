<?php

namespace Tests\Feature;

use App\Exceptions\RouteNotPossible;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Models\MapEndPoint;
use App\Models\MapPath;
use App\Models\User;
use App\Services\ApiKeyIssuer;
use App\Services\EdgeStatusService;
use App\Services\MapEditService;
use App\Services\Routing\RoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * ISM-114 / ISM-115: administrators add points (node, shop entrance,
 * facility, QR code) and draw paths on a map; each has a unique key and
 * works like the package's own records. Coordinates refer to the pilot
 * fixture (MallMapFactory::pilotPackage()): ground floor walkways along y = 0
 * from G-C01 (0,0) through G-N0001 (10,0) to G-N0002 (20,0), up x = 10 to
 * G-N0003 (10,9), and from G-N0002 to the door E-G-101 (20,3).
 */
class MapEditTest extends TestCase
{
    use RefreshDatabase;

    private MallMap $map;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->map = $this->pilotMap();
    }

    private function edits(): MapEditService
    {
        return app(MapEditService::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function point(array $data): MapEndPoint
    {
        return $this->edits()->createPoint($this->map, $data + ['kind' => 'node', 'name' => 'Kiosk', 'floor_code' => 'G']);
    }

    /**
     * @param  list<string>|string  $to
     * @return array<string, mixed>
     */
    private function route(string $from, array|string $to, bool $stepFree = false, ?MallMap $map = null): array
    {
        return app(RoutePlanner::class)->plan($map ?? $this->map, $from, (array) $to, $stepFree);
    }

    /**
     * @param  array<string, mixed>  $route
     * @return list<string>
     */
    private function codes(array $route): array
    {
        return array_column($route['path'], 'node_code');
    }

    private function assertUnreachable(string $from, string $to, bool $stepFree = false): void
    {
        try {
            $this->route($from, $to, $stepFree);
            $this->fail("{$from} → {$to} should have no route.");
        } catch (RouteNotPossible $error) {
            $this->assertSame('unreachable', $error->reason);
        }
    }

    public function test_a_node_point_gets_a_unique_key_and_joins_the_nearest_walkway(): void
    {
        $point = $this->point(['x' => 15.0, 'y' => 1.5]);
        $other = $this->point(['name' => 'Bench', 'x' => 15.5, 'y' => -1.0]);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $point->key);
        $this->assertNotSame($point->key, $other->key);
        $this->assertSame(1.5, $point->link_m);
        $this->assertSame(['G-C01', 'G-N0001', "P-{$point->key}-J", "P-{$point->key}"], $this->codes($this->route('node:G-C01', $point->place())));
        $this->assertSame('Arrive at Kiosk.', end($this->route('node:G-C01', $point->place())['steps'])['instruction']);
    }

    public function test_a_point_near_a_walkway_end_joins_that_node_without_a_junction(): void
    {
        $point = $this->point(['x' => 10.1, 'y' => 0.1]);

        $this->assertNull(DB::table('map_nodes')->where('node_code', "P-{$point->key}-J")->first());
        $this->assertSame(['G-C01', 'G-N0001', "P-{$point->key}"], $this->codes($this->route('node:G-C01', $point->place())));
    }

    public function test_a_shop_entrance_point_works_as_a_door_of_its_unit(): void
    {
        $door = $this->point(['kind' => 'shop_entrance', 'name' => 'Gadget Hub door', 'unit_code' => 'G-05', 'is_primary' => true, 'x' => 16.0, 'y' => -1.0]);

        $route = $this->route('node:G-C01', 'unit:G-05');
        $this->assertSame("P-{$door->key}", $route['to'][0]['node_code']);
        $this->assertSame('Shop G-05', $route['to'][0]['label']);
        $this->assertSame(17.0, $route['total']['length_m']);
        $this->assertSame('entrance', DB::table('map_nodes')->where('id', $door->map_node_id)->value('node_type'));
        $this->assertTrue((bool) DB::table('map_shop_entrances')->where('unit_code', 'G-05')->value('is_primary'));
    }

    public function test_a_second_main_door_is_refused_but_a_side_door_is_used_when_nearer(): void
    {
        try {
            $this->point(['kind' => 'shop_entrance', 'name' => 'G-101 second door', 'unit_code' => 'G-101', 'is_primary' => true, 'x' => 19.0, 'y' => 1.0]);
            $this->fail('A second main door was accepted.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('Unit G-101 already has a main door', $error->getMessage());
        }

        $side = $this->point(['kind' => 'shop_entrance', 'name' => 'G-101 corner door', 'unit_code' => 'G-101', 'x' => 15.0, 'y' => 0.5]);

        $this->assertSame("P-{$side->key}", $this->route('node:G-C01', 'unit:G-101')['to'][0]['node_code']);
        $this->assertSame(3, DB::table('map_shop_entrances')->where('unit_code', 'G-101')->count());
    }

    public function test_a_facility_point_is_found_by_name_and_as_the_nearest_of_its_type(): void
    {
        $toilet = $this->point(['kind' => 'facility', 'name' => 'Kiosk toilets', 'facility_type' => 'toilet', 'x' => 2.0, 'y' => 1.0]);

        $this->assertSame("P-{$toilet->key}", $this->route('node:G-C01', 'facility-type:toilet')['to'][0]['node_code']);
        $this->assertSame('Kiosk toilets', $this->route('node:G-C01', 'facility:Kiosk toilets')['to'][0]['label']);

        $this->expectExceptionMessage('A facility called “Toilets G” is already on this map');
        $this->point(['kind' => 'facility', 'name' => 'Toilets G', 'facility_type' => 'toilet', 'x' => 3.0, 'y' => 1.0]);
    }

    public function test_a_qr_code_point_hangs_on_a_wall_and_can_be_scanned(): void
    {
        $qr = $this->point(['kind' => 'checkpoint', 'name' => 'Corridor wall, facing north', 'checkpoint_code' => 'G-C10', 'x' => 12.0, 'y' => -0.5, 'qr_height' => 1.4, 'qr_yaw' => 90]);

        $node = DB::table('map_nodes')->where('id', $qr->map_node_id)->first();
        $this->assertEqualsWithDelta(12.0, $node->x_m, 0.001);
        $this->assertEqualsWithDelta(0.3, $node->y_m, 0.001);
        $this->assertSame('checkpoint', $node->node_type);
        $this->assertEquals(['x_m' => 12.0, 'y_m' => -0.5, 'z_m' => 1.4, 'yaw_deg' => 90.0, 'marker_height_m' => 1.4, 'physical_width_m' => 0.18], $qr->qr);

        $checkpoint = Checkpoint::query()->where('mall_ref', 'demo-mall')->where('checkpoint_code', 'G-C10')->sole();
        $this->assertSame('Corridor wall, facing north', $checkpoint->label);
        [, $key] = app(ApiKeyIssuer::class)->issue('App', ['maps:read']);
        $this->withToken($key)->getJson("/api/v1/checkpoints/{$checkpoint->public_slug}")
            ->assertOk()->assertJsonPath('data.node.node_code', "P-{$qr->key}");
        $this->assertSame('E-1-205', $this->route("checkpoint:{$checkpoint->public_slug}", 'unit:1-205')['to'][0]['node_code']);
    }

    public function test_bad_qr_codes_are_refused(): void
    {
        foreach ([
            [['checkpoint_code' => 'G-C01'], 'QR code G-C01 is already on this map'],
            [['checkpoint_code' => 'bad code!'], 'Give the QR code a code'],
            [['checkpoint_code' => 'G-C11', 'qr_height' => 5.0], 'between 0.2 and 3 m'],
        ] as [$data, $message]) {
            try {
                $this->point($data + ['kind' => 'checkpoint', 'name' => 'QR', 'x' => 12.0, 'y' => -0.5, 'qr_yaw' => 90]);
                $this->fail('Expected a refusal.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
        $this->assertSame(0, MapEndPoint::query()->count());
    }

    public function test_a_point_far_from_walkways_waits_for_a_path_that_then_reaches_it(): void
    {
        $point = $this->point(['x' => 30.0, 'y' => 5.0]);
        $this->assertTrue($point->isLinked());
        $this->assertNull($point->link_m);
        $this->assertUnreachable('node:G-C01', $point->place());

        $path = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0], [30.0, 5.0]]);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $path->key);
        $route = $this->route('node:G-C01', $point->place());
        $this->assertSame(['G-C01', 'G-N0001', 'G-N0002', 'E-G-101', "W-{$path->key}-2", "P-{$point->key}"], $this->codes($route));
        $this->assertSame(35.0, $route['total']['length_m']);
    }

    public function test_a_path_corner_near_a_walkway_splits_it_and_points_can_join_the_path(): void
    {
        $path = $this->edits()->createPath($this->map, 'G', [[15.0, 0.3], [15.0, 5.0]]);
        $junction = DB::table('map_nodes')->where('node_code', "W-{$path->key}-1")->first();
        $this->assertEquals([15.0, 0.0], [(float) $junction->x_m, (float) $junction->y_m]);

        $point = $this->point(['x' => 15.2, 'y' => 5.2]);

        $this->assertSame(['G-C01', 'G-N0001', "W-{$path->key}-1", "W-{$path->key}-2", "P-{$point->key}"], $this->codes($this->route('node:G-C01', $point->place())));

        app(EdgeStatusService::class)->set($this->map, 'G-N0001', 'G-N0002', 'closed');
        $this->assertUnreachable('node:G-C01', $point->place());
    }

    public function test_one_way_and_not_step_free_paths_are_respected(): void
    {
        $point = $this->point(['x' => 30.0, 'y' => 5.0]);
        $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0], [30.0, 5.0]], stepFree: false, oneWay: true);

        $this->assertSame(35.0, $this->route('node:G-C01', $point->place())['total']['length_m']);
        $this->assertUnreachable($point->place(), 'node:G-C01');
        $this->assertUnreachable('node:G-C01', $point->place(), stepFree: true);
    }

    public function test_bad_paths_are_refused(): void
    {
        foreach ([
            [[[1.0, 1.0]], 'A path needs 2 to 100 corners'],
            [[[1.0, 1.0], [1.01, 1.0]], 'in the same place'],
            [[[10.0, 0.1], [10.2, 0.2]], 'adds no walkway'],
        ] as [$corners, $message]) {
            try {
                $this->edits()->createPath($this->map, 'G', $corners);
                $this->fail('Expected a refusal.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
        $this->assertSame(0, MapPath::query()->count());
        $this->expectExceptionMessage('This map has no floor 9');
        $this->edits()->createPath($this->map, '9', [[0.0, 0.0], [5.0, 0.0]]);
    }

    public function test_deleting_edits_leaves_the_package_graph_and_keeps_other_closures(): void
    {
        $nodes = $this->map->nodes()->count();
        $edges = $this->map->edges()->count();
        $path = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0]]);
        $point = $this->point(['x' => 15.0, 'y' => 1.5]);
        app(EdgeStatusService::class)->set($this->map, 'E-G-101', "W-{$path->key}-2", 'closed', 'Cleaning');

        $this->edits()->deletePoint($point);

        $closed = DB::table('map_edges')->where('map_path_id', $path->id)->where('status', 'closed')->get();
        $this->assertCount(2, $closed);
        $this->assertSame('Cleaning', $closed[0]->closed_reason);

        $this->edits()->deletePath($path);
        $this->assertSame($nodes, $this->map->nodes()->count());
        $this->assertSame($edges, $this->map->edges()->count());
    }

    public function test_rebuilding_and_a_new_map_version_keep_points_paths_and_keys(): void
    {
        $point = $this->point(['x' => 30.0, 'y' => 5.0]);
        $path = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0], [30.0, 5.0]]);
        $qr = $this->point(['kind' => 'checkpoint', 'name' => 'Wall QR', 'checkpoint_code' => 'G-C10', 'x' => 12.0, 'y' => -0.5, 'qr_yaw' => 90]);

        $this->artisan('map:rebuild-graph', ['map' => $this->map->id])->assertSuccessful();
        $this->assertSame(35.0, $this->route('node:G-C01', $point->place())['total']['length_m']);

        $version = $this->pilotMap('demo-mall');
        $this->assertSame([$point->key, $qr->key], MapEndPoint::query()->where('mall_map_id', $version->id)->orderBy('sequence')->pluck('key')->all());
        $this->assertSame($path->key, MapPath::query()->where('mall_map_id', $version->id)->value('key'));
        $this->assertSame(35.0, $this->route('node:G-C01', $point->place(), map: $version)['total']['length_m']);
        $slug = Checkpoint::query()->where('checkpoint_code', 'G-C10')->value('public_slug');
        $this->assertSame("P-{$qr->key}", $this->route("checkpoint:{$slug}", 'unit:G-101', map: $version)['from']['node_code']);
    }

    public function test_administrators_add_points_and_paths_through_json_and_see_keys_and_drawn_walkways(): void
    {
        $this->actingAs(User::factory()->create());

        $door = $this->postJson(route('admin.maps.end-points.store', $this->map), ['kind' => 'shop_entrance', 'name' => 'Gadget Hub door', 'unit_code' => 'G-05', 'is_primary' => true, 'floor_code' => 'G', 'x' => 16, 'y' => -1])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'shop_entrance')
            ->assertJsonPath('data.kind_label', 'Shop entrance')
            ->assertJsonPath('data.connected', true)
            ->json('data');
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $door['key']);

        $qr = $this->postJson(route('admin.maps.end-points.store', $this->map), ['kind' => 'checkpoint', 'name' => 'Wall QR', 'checkpoint_code' => 'G-C10', 'floor_code' => 'G', 'x' => 12, 'y' => -0.5, 'qr_z' => 1.5, 'qr_yaw' => 90])
            ->assertCreated()->assertJsonPath('data.qr.marker_height_m', 1.5)->json('data');
        $this->assertStringContainsString('/malls/demo-mall/map/qr/', $qr['qr_url']);

        $this->postJson(route('admin.maps.paths.store', $this->map), ['floor_code' => 'G', 'points' => [[20, 3], [30, 3]], 'step_free' => true, 'one_way' => false, 'name' => 'Shortcut'])
            ->assertCreated()->assertJsonPath('data.joined', true)->assertJsonPath('data.length_m', 10)->assertJsonPath('data.corners', 2);

        $index = $this->getJson(route('admin.maps.end-points', $this->map))->assertOk();
        $this->assertCount(2, $index->json('data'));
        $this->assertSame('Shortcut', $index->json('paths.0.name'));
        $this->assertContains('path', array_column($index->json('overlay.edges'), 'kind'));
        $this->assertContains('link', array_column($index->json('overlay.edges'), 'kind'));

        $this->postJson(route('admin.maps.end-points.store', $this->map), ['kind' => 'shop_entrance', 'name' => 'Door', 'floor_code' => 'G', 'x' => 1, 'y' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_code');
        $this->postJson(route('admin.maps.end-points.store', $this->map), ['kind' => 'checkpoint', 'name' => 'QR', 'floor_code' => 'G', 'x' => 1, 'y' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['checkpoint_code', 'qr_yaw']);
        $this->postJson(route('admin.maps.end-points.store', $this->map), ['kind' => 'statue', 'name' => 'X', 'floor_code' => 'G', 'x' => 1, 'y' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->postJson(route('admin.maps.end-points.store', $this->map), ['kind' => 'facility', 'name' => 'Toilets G', 'facility_type' => 'toilet', 'floor_code' => 'G', 'x' => 1, 'y' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('point');
    }

    public function test_the_points_and_paths_page_adds_with_forms_and_lists_keys(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.maps.end-points.store', $this->map), ['kind' => 'facility', 'name' => 'Kiosk toilets', 'facility_type' => 'toilet', 'floor_code' => 'G', 'x' => 2, 'y' => 1])
            ->assertRedirect()->assertSessionHas('status');
        $this->post(route('admin.maps.paths.store', $this->map), ['floor_code' => 'G', 'corners' => "20, 3\n30 3\n30;5", 'step_free' => '0', 'one_way' => '0'])
            ->assertRedirect()->assertSessionHas('status');
        $path = MapPath::query()->sole();
        $this->assertSame([[20, 3], [30, 3], [30, 5]], $path->points);
        $this->assertFalse($path->is_accessible);

        $this->post(route('admin.maps.paths.store', $this->map), ['floor_code' => 'G', 'corners' => "20, 3\nnorth"])->assertSessionHasErrors('corners');

        $point = MapEndPoint::query()->sole();
        $this->get(route('admin.maps.end-points', $this->map))->assertOk()
            ->assertSee($point->key)->assertSee('Kiosk toilets')->assertSee($path->key)->assertSee('joined to the map');

        $this->patch(route('admin.maps.paths.update', [$this->map, $path]), ['name' => 'Back corridor'])->assertRedirect();
        $this->assertSame('Back corridor', $path->refresh()->name);
        $this->patch(route('admin.maps.end-points.update', [$this->map, $point]), ['name' => 'Toilets G'])->assertSessionHasErrors('point');
        $this->delete(route('admin.maps.paths.destroy', [$this->map, $path]))->assertRedirect();
        $this->assertSame(0, MapPath::query()->count());

        $other = $this->pilotMap('other-mall');
        $this->delete(route('admin.maps.end-points.destroy', [$other, $point]))->assertNotFound();
    }

    public function test_the_embedded_map_offers_node_points_and_labels_them(): void
    {
        $point = $this->point(['x' => 15.0, 'y' => 1.5]);
        $this->point(['kind' => 'shop_entrance', 'name' => 'Gadget Hub door', 'unit_code' => 'G-05', 'x' => 16.0, 'y' => -1.0]);
        [, $key] = app(ApiKeyIssuer::class)->issue('App', ['maps:read'], null, ['http://127.0.0.1:8000']);

        $this->withToken($key)->getJson('/api/v1/malls/demo-mall')
            ->assertOk()
            ->assertJsonPath('data.places.end_points.0.kind', 'shop_entrance')
            ->assertJsonPath('data.places.end_points.1', ['key' => $point->key, 'kind' => 'node', 'name' => 'Kiosk', 'floor_code' => 'G', 'place' => $point->place()])
            ->assertJsonFragment(['unit_code' => 'G-05', 'floor_code' => 'G', 'entrances' => 1, 'place' => 'unit:G-05']);

        $url = $this->withToken($key)->postJson('/api/v1/embed-links', ['mall_ref' => 'demo-mall', 'to' => $point->place()])->assertCreated()->json('data.url');
        preg_match('#id="embed-config">(.*?)</script>#s', $this->get($url)->assertOk()->getContent(), $match);
        $token = json_decode(html_entity_decode($match[1]), true)['token'];
        $this->withHeader('X-Embed-Token', $token)->getJson(route('embed.data', $this->map))
            ->assertOk()->assertJsonPath("data.labels.P-{$point->key}", 'Kiosk');
    }

    public function test_guests_cannot_see_or_change_points_and_paths(): void
    {
        $point = $this->point(['x' => 15.0, 'y' => 1.5]);
        $path = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0]]);

        $this->get(route('admin.maps.end-points', $this->map))->assertRedirect(route('login'));
        $this->post(route('admin.maps.end-points.store', $this->map), ['kind' => 'node', 'name' => 'X', 'floor_code' => 'G', 'x' => 15, 'y' => 1])->assertRedirect(route('login'));
        $this->post(route('admin.maps.paths.store', $this->map), ['floor_code' => 'G', 'corners' => "1,1\n5,1"])->assertRedirect(route('login'));
        $this->delete(route('admin.maps.end-points.destroy', [$this->map, $point]))->assertRedirect(route('login'));
        $this->delete(route('admin.maps.paths.destroy', [$this->map, $path]))->assertRedirect(route('login'));
        $this->assertSame(1, MapEndPoint::query()->count());
        $this->assertSame(1, MapPath::query()->count());
    }

    public function test_points_and_qr_codes_get_a_default_height_that_can_be_changed(): void
    {
        $node = $this->point(['x' => 15.0, 'y' => 1.5]);
        $sign = $this->point(['name' => 'Hanging sign', 'x' => 16.0, 'y' => 1.5, 'height' => 2.6]);
        $qr = $this->point(['kind' => 'checkpoint', 'name' => 'Wall QR', 'checkpoint_code' => 'G-C10', 'x' => 12.0, 'y' => -0.5, 'qr_yaw' => 90]);

        $this->assertSame(1.5, $node->height_m);
        $this->assertSame(2.6, $sign->height_m);
        $this->assertSame(1.4, $qr->height_m);
        $this->assertSame(1.4, $qr->qr['marker_height_m']);

        $qr = $this->edits()->updatePoint($qr, ['height' => 1.75]);
        $this->assertSame(1.75, $qr->qr['marker_height_m']);
        $this->assertSame(1.75, $qr->qr['z_m']);

        try {
            $this->edits()->updatePoint($qr, ['height' => 3.5]);
            $this->fail('A QR code at 3.5 m was accepted.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('between 0.2 and 3 m', $error->getMessage());
        }
        $this->assertSame(1.75, $qr->refresh()->height_m);
    }

    public function test_editing_a_point_moves_it_changes_its_kind_and_keeps_its_key(): void
    {
        $point = $this->point(['x' => 15.0, 'y' => 1.5]);
        $key = $point->key;

        $moved = $this->edits()->updatePoint($point, ['x' => 2.0, 'y' => 1.0]);
        $this->assertSame($key, $moved->key);
        $this->assertSame(3.0, $this->route('node:G-C01', $moved->place())['total']['length_m']);

        $toilet = $this->edits()->updatePoint($moved, ['kind' => 'facility', 'facility_type' => 'toilet', 'name' => 'Kiosk toilets']);
        $this->assertSame('facility', $toilet->kind);
        $this->assertSame("P-{$key}", $this->route('node:G-C01', 'facility-type:toilet')['to'][0]['node_code']);
        $this->assertSame(1, DB::table('map_facility_anchors')->where('map_end_point_id', $toilet->id)->count());

        // Saving it again with its own name is not a clash with itself.
        $this->edits()->updatePoint($toilet, ['name' => 'Kiosk toilets', 'height' => 1.2]);
        $this->assertSame(1.2, $toilet->refresh()->height_m);

        $this->expectExceptionMessage('A facility called “Toilets G” is already on this map');
        $this->edits()->updatePoint($toilet, ['name' => 'Toilets G']);
    }

    public function test_editing_a_path_redraws_it_and_changes_its_flags(): void
    {
        $point = $this->point(['x' => 30.0, 'y' => 5.0]);
        $path = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0], [30.0, 5.0]], name: 'East shortcut');
        $this->assertSame(35.0, $this->route('node:G-C01', $point->place())['total']['length_m']);

        $path = $this->edits()->updatePath($path, ['points' => [[20.0, 0.0], [30.0, 0.0], [30.0, 5.0]]]);
        $this->assertSame('East shortcut', $path->name);
        $this->assertSame(35.0, $this->route('node:G-C01', $point->place())['total']['length_m']);
        $this->assertSame(['G-C01', 'G-N0001', 'G-N0002', "W-{$path->key}-2", "P-{$point->key}"], $this->codes($this->route('node:G-C01', $point->place())));

        $this->edits()->updatePath($path, ['step_free' => false]);
        $this->assertUnreachable('node:G-C01', $point->place(), stepFree: true);

        $this->expectExceptionMessage('A path needs 2 to 100 corners');
        $this->edits()->updatePath($path, ['points' => [[20.0, 0.0]]]);
    }

    public function test_two_paths_that_meet_at_an_end_merge_into_one(): void
    {
        $point = $this->point(['x' => 30.0, 'y' => 8.0]);
        $first = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0]], name: 'First');
        $second = $this->edits()->createPath($this->map, 'G', [[30.2, 3.1], [30.0, 8.0]]);
        $separate = $this->route('node:G-C01', $point->place())['total']['length_m'];

        $merged = $this->edits()->mergePaths($first, $second);

        $this->assertSame($first->key, $merged->key);
        $this->assertSame('First', $merged->name);
        $this->assertEquals([[20, 3], [30, 3], [30, 8]], $merged->points);
        $this->assertNull(MapPath::query()->find($second->id));
        $this->assertEqualsWithDelta($separate, $this->route('node:G-C01', $point->place())['total']['length_m'], 0.3);
    }

    public function test_paths_that_do_not_meet_or_run_against_each_other_are_not_merged(): void
    {
        $a = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0]]);
        $b = $this->edits()->createPath($this->map, 'G', [[35.0, 3.0], [40.0, 3.0]]);
        try {
            $this->edits()->mergePaths($a, $b);
            $this->fail('Paths that do not meet were merged.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('do not meet at an end', $error->getMessage());
        }

        $c = $this->edits()->createPath($this->map, 'G', [[20.0, 0.0], [26.0, -4.0]], oneWay: true);
        $d = $this->edits()->createPath($this->map, 'G', [[30.0, -4.0], [26.0, -4.0]], oneWay: true);
        $this->expectExceptionMessage('run against each other');
        $this->edits()->mergePaths($c, $d);
    }

    public function test_administrators_edit_and_merge_through_json_and_forms(): void
    {
        $this->actingAs(User::factory()->create());
        $point = $this->point(['x' => 15.0, 'y' => 1.5]);
        $first = $this->edits()->createPath($this->map, 'G', [[20.0, 3.0], [30.0, 3.0]]);
        $second = $this->edits()->createPath($this->map, 'G', [[30.0, 3.0], [30.0, 8.0]]);

        $this->patchJson(route('admin.maps.end-points.update', [$this->map, $point]), ['x' => 2, 'y' => 1, 'height' => 2.2, 'name' => 'Moved kiosk'])
            ->assertOk()
            ->assertJsonPath('data.key', $point->key)
            ->assertJsonPath('data.name', 'Moved kiosk')
            ->assertJsonPath('data.height_m', 2.2)
            ->assertJsonPath('data.position.x', 2);

        $this->patch(route('admin.maps.paths.update', [$this->map, $first]), ['name' => 'Corridor', 'corners' => "20, 3\n29, 3\n30, 3", 'step_free' => '1', 'one_way' => '0'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame(3, count($first->refresh()->points));

        $this->postJson(route('admin.maps.paths.merge', [$this->map, $first]), ['with' => 'nosuchkey1'])->assertUnprocessable()->assertJsonValidationErrors('with');
        $this->postJson(route('admin.maps.paths.merge', [$this->map, $first]), ['with' => $second->key])
            ->assertOk()->assertJsonPath('data.key', $first->key)->assertJsonPath('data.corners', 4);
        $this->assertSame(1, MapPath::query()->count());

        $this->get(route('admin.maps.end-points', $this->map))->assertOk()->assertSee('Moved kiosk')->assertSee('2.20 m high')->assertSee('Save path');
    }
}
