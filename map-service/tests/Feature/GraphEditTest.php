<?php

namespace Tests\Feature;

use App\Exceptions\RouteNotPossible;
use App\Models\MallMap;
use App\Models\MapGraphEdit;
use App\Models\User;
use App\Services\EdgeStatusService;
use App\Services\MapEditService;
use App\Services\Routing\RoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * ISM-114: administrators edit the navigation graph itself. Pilot fixture
 * (MallMapFactory::pilotPackage()): ground floor G-C01 (0,0) – G-N0001
 * (10,0) – G-N0002 (20,0) – door E-G-101 (20,3); G-N0001 – G-N0003 (10,9) –
 * Toilets G (10,11); G-C01 – lift (0,10) – G-N0003; stairs at G-N0002.
 */
class GraphEditTest extends TestCase
{
    use RefreshDatabase;

    private MallMap $map;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->map = $this->pilotMap();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function change(array $data): MapGraphEdit
    {
        return app(MapEditService::class)->createGraphEdit($this->map, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function route(string $from, string $to, bool $stepFree = false, ?MallMap $map = null): array
    {
        return app(RoutePlanner::class)->plan($map ?? $this->map, $from, [$to], $stepFree);
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

    public function test_moving_a_node_changes_the_lengths_of_its_walkways(): void
    {
        $edit = $this->change(['action' => 'move_node', 'node_code' => 'G-N0001', 'x' => 10.0, 'y' => 2.0]);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $edit->key);
        $this->assertSame('Move node G-N0001 to x 10.00, y 2.00', $edit->describe());
        $node = DB::table('map_nodes')->where('mall_map_id', $this->map->id)->where('node_code', 'G-N0001')->first();
        $this->assertEquals([10.0, 2.0], [(float) $node->x_m, (float) $node->y_m]);
        $this->assertSame(20.4, $this->route('node:G-C01', 'node:G-N0002')['total']['length_m']);
    }

    public function test_removing_a_walkway_makes_routes_go_another_way(): void
    {
        $this->change(['action' => 'remove_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002']);

        // The main door of G-101 hangs off G-N0002, now reachable only down the stairs from the first floor.
        $route = $this->route('node:G-C01', 'node:E-G-101');
        $this->assertContains('G-X-Main-Stairs', $this->codes($route));
        $this->assertSame(['G-N0002', 'E-G-101'], array_slice($this->codes($route), -2));
        // The side door of G-101 is still reached directly.
        $this->assertSame('E-G-101-B', $this->route('node:G-C01', 'unit:G-101')['to'][0]['node_code']);
        $this->assertUnreachable('node:G-C01', 'node:E-G-101', stepFree: true);
    }

    public function test_removing_a_walkway_also_removes_the_halves_a_point_added_on_it(): void
    {
        $point = app(MapEditService::class)->createPoint($this->map, ['kind' => 'node', 'name' => 'Kiosk', 'floor_code' => 'G', 'x' => 15.0, 'y' => 1.5]);
        $this->assertSame(16.5, $this->route('node:G-C01', $point->place())['total']['length_m']);

        $this->change(['action' => 'remove_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002']);

        $this->assertUnreachable('node:G-C01', $point->place());
    }

    public function test_removing_a_node_removes_its_walkways_but_not_doors_or_lift_stops(): void
    {
        $this->change(['action' => 'remove_node', 'node_code' => 'G-N0003']);
        $this->assertUnreachable('node:G-C01', 'facility:Toilets G');

        foreach ([['E-G-101', 'is a shop entrance'], ['G-X-Central-Lift', 'is a stop of a lift'], ['G-F-Toilets-G', 'is a facility'], ['G-C01', 'is where visitors stand at a QR code'], ['G-N9999', 'is not on this map']] as [$code, $message]) {
            try {
                $this->change(['action' => 'remove_node', 'node_code' => $code]);
                $this->fail("Removing {$code} was accepted.");
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
    }

    public function test_connecting_two_nodes_adds_a_walkway(): void
    {
        $this->assertSame(21.0, $this->route('node:G-C01', 'facility:Toilets G')['total']['length_m']);

        $this->change(['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003']);

        $this->assertSame(['G-C01', 'G-N0003', 'G-F-Toilets-G'], $this->codes($this->route('node:G-C01', 'facility:Toilets G')));
        $this->assertSame(15.5, $this->route('node:G-C01', 'facility:Toilets G')['total']['length_m']);

        foreach ([[['from_code' => 'G-C01', 'to_code' => 'G-N0001'], 'already joined'], [['from_code' => 'G-C01', 'to_code' => '1-C01'], 'same floor'], [['from_code' => 'G-C01', 'to_code' => 'G-C01'], 'two different nodes']] as [$data, $message]) {
            try {
                $this->change(['action' => 'connect'] + $data);
                $this->fail('Expected a refusal.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
    }

    public function test_a_walkway_can_be_made_one_way_or_not_step_free(): void
    {
        $this->change(['action' => 'edit_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002', 'direction' => 'forward']);
        $this->assertSame(20.0, $this->route('node:G-C01', 'node:G-N0002')['total']['length_m']);
        $this->assertContains('G-X-Main-Stairs', $this->codes($this->route('node:G-N0002', 'node:G-C01')));

        $this->change(['action' => 'edit_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002', 'direction' => 'both']);
        $this->assertSame(20.0, $this->route('node:G-N0002', 'node:G-C01')['total']['length_m']);

        $this->change(['action' => 'edit_edge', 'from_code' => 'G-C01', 'to_code' => 'G-N0001', 'step_free' => false]);
        $this->assertContains('G-X-Central-Lift', $this->codes($this->route('node:G-C01', 'node:G-N0002', stepFree: true)));
        $this->assertSame(20.0, $this->route('node:G-C01', 'node:G-N0002')['total']['length_m']);
    }

    public function test_undoing_a_change_restores_the_graph_and_other_changes_stay(): void
    {
        $removal = $this->change(['action' => 'remove_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002']);
        $this->change(['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003']);
        app(EdgeStatusService::class)->set($this->map, 'G-C01', 'G-X-Central-Lift', 'closed', 'Repairs');

        app(MapEditService::class)->deleteGraphEdit($removal);

        $this->assertSame(20.0, $this->route('node:G-C01', 'node:G-N0002')['total']['length_m']);
        $this->assertSame(15.5, $this->route('node:G-C01', 'facility:Toilets G')['total']['length_m']);
        $this->assertSame(2, $this->map->edges()->where('status', 'closed')->count());
    }

    public function test_changes_survive_a_rebuild_and_move_to_a_new_map_version(): void
    {
        $this->change(['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003']);
        $this->change(['action' => 'remove_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002']);

        $this->artisan('map:rebuild-graph', ['map' => $this->map->id])->assertSuccessful();
        $this->assertSame(15.5, $this->route('node:G-C01', 'facility:Toilets G')['total']['length_m']);

        $version = $this->pilotMap('demo-mall');
        $this->assertSame(2, MapGraphEdit::query()->where('mall_map_id', $version->id)->count());
        $this->assertSame(15.5, $this->route('node:G-C01', 'facility:Toilets G', map: $version)['total']['length_m']);
        $this->assertContains('G-X-Main-Stairs', $this->codes($this->route('node:G-C01', 'node:E-G-101', map: $version)));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function edit(MapGraphEdit $edit, array $changes): MapGraphEdit
    {
        return app(MapEditService::class)->updateGraphEdit($edit, $changes);
    }

    /**
     * The message of an edit that must be refused.
     *
     * @param  array<string, mixed>  $changes
     */
    private function refusal(MapGraphEdit $edit, array $changes): string
    {
        try {
            $this->edit($edit, $changes);
        } catch (InvalidArgumentException $error) {
            return $error->getMessage();
        }
        $this->fail('The edit was accepted.');
    }

    public function test_editing_a_move_gives_the_node_its_new_place_and_keeps_the_key_and_order(): void
    {
        $move = $this->change(['action' => 'move_node', 'node_code' => 'G-N0001', 'x' => 10.0, 'y' => 2.0]);
        $later = $this->change(['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003']);

        $edited = $this->edit($move, ['y' => 4.0]);

        $this->assertSame([$move->key, $move->sequence], [$edited->key, $edited->sequence]);
        $this->assertSame('Move node G-N0001 to x 10.00, y 4.00', $edited->describe());
        $this->assertSame(21.5, $this->route('node:G-C01', 'node:G-N0002')['total']['length_m']);
        $this->assertNull($later->refresh()->problem);
        $this->assertSame(15.5, $this->route('node:G-C01', 'facility:Toilets G')['total']['length_m']);
        $this->assertSame(2, MapGraphEdit::query()->count());
    }

    public function test_editing_a_connection_changes_its_settings_or_its_nodes_and_replays_later_changes(): void
    {
        $connect = $this->change(['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003']);
        $oneWay = $this->change(['action' => 'edit_edge', 'from_code' => 'G-C01', 'to_code' => 'G-N0003', 'direction' => 'forward']);

        $this->edit($connect, ['step_free' => false]);

        $this->assertNull($oneWay->refresh()->problem);
        $edges = DB::table('map_edges as e')->join('map_nodes as a', 'a.id', '=', 'e.from_node_id')->join('map_nodes as b', 'b.id', '=', 'e.to_node_id')
            ->where('e.mall_map_id', $this->map->id)->where(fn ($q) => $q->where(fn ($w) => $w->where('a.node_code', 'G-C01')->where('b.node_code', 'G-N0003'))->orWhere(fn ($w) => $w->where('a.node_code', 'G-N0003')->where('b.node_code', 'G-C01')))
            ->get(['a.node_code as from', 'e.is_accessible']);
        $this->assertCount(1, $edges, 'The later one-way change still applies.');
        $this->assertSame(['G-C01', false], [$edges[0]->from, (bool) $edges[0]->is_accessible]);
        $this->assertSame(21.0, $this->route('node:G-C01', 'facility:Toilets G', stepFree: true)['total']['length_m']);

        $this->edit($connect, ['to_code' => 'E-G-101', 'from_code' => 'G-N0003', 'step_free' => true]);

        $this->assertSame('Connect G-N0003 and E-G-101', $connect->refresh()->describe());
        $this->assertSame(['G-F-Toilets-G', 'G-N0003', 'E-G-101'], $this->codes($this->route('facility:Toilets G', 'node:E-G-101')));
        $this->assertSame('No walkway joins G-C01 and G-N0003.', $oneWay->refresh()->problem, 'The later change no longer fits and is shown as not applied.');
    }

    public function test_an_edit_that_does_not_fit_is_refused_and_the_graph_stays_as_it_was(): void
    {
        $removal = $this->change(['action' => 'remove_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002']);
        $connect = $this->change(['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003']);
        $point = app(MapEditService::class)->createPoint($this->map, ['kind' => 'node', 'name' => 'Kiosk', 'floor_code' => 'G', 'x' => 15.0, 'y' => 6.0]);
        $pointNode = DB::table('map_nodes')->where('id', $point->refresh()->map_node_id)->value('node_code');
        $move = $this->change(['action' => 'move_node', 'node_code' => 'G-N0001', 'x' => 10.0, 'y' => 2.0]);

        $this->assertStringContainsString('Node G-N9999 is not on this map', $this->refusal($removal, ['to_code' => 'G-N9999']));
        $this->assertStringContainsString('No walkway joins G-C01 and G-N0002', $this->refusal($removal, ['from_code' => 'G-C01']));
        $this->assertStringContainsString('same floor', $this->refusal($connect, ['to_code' => '1-C01']));
        $this->assertStringContainsString('already joined', $this->refusal($connect, ['to_code' => 'G-N0001']));
        $this->assertStringContainsString('two different nodes', $this->refusal($connect, ['to_code' => 'G-C01']));
        $this->assertStringContainsString('belongs to a point', $this->refusal($move, ['node_code' => $pointNode]));
        $this->assertStringContainsString('x and y', $this->refusal($move, ['x' => 'east']));

        $this->assertSame(['G-N0001', 'G-N0002', null], [$removal->refresh()->from_code, $removal->to_code, $removal->problem]);
        $this->assertSame('Move node G-N0001 to x 10.00, y 2.00', $move->refresh()->describe());
        $this->assertContains('G-X-Main-Stairs', $this->codes($this->route('node:G-C01', 'node:E-G-101')));
        $this->assertSame(15.5, $this->route('node:G-C01', 'facility:Toilets G')['total']['length_m']);
    }

    public function test_a_change_that_no_longer_applies_can_be_fixed_by_editing_its_nodes(): void
    {
        $removal = $this->change(['action' => 'remove_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002']);
        // As after importing a version whose Blender export named the node differently.
        $removal->forceFill(['to_code' => 'G-N0002-old'])->save();
        app(MapEditService::class)->rebuild($this->map);
        $this->assertSame('Node G-N0002-old is not on this map.', $removal->refresh()->problem);
        $this->assertSame(20.0, $this->route('node:G-C01', 'node:G-N0002')['total']['length_m']);

        $this->edit($removal, ['to_code' => 'G-N0002']);

        $this->assertNull($removal->refresh()->problem);
        $this->assertContains('G-X-Main-Stairs', $this->codes($this->route('node:G-C01', 'node:E-G-101')));
    }

    public function test_the_json_and_the_page_form_edit_a_change(): void
    {
        $this->actingAs(User::factory()->create());
        $move = $this->change(['action' => 'move_node', 'node_code' => 'G-N0001', 'x' => 10.0, 'y' => 2.0]);
        $walkway = $this->change(['action' => 'edit_edge', 'from_code' => 'G-C01', 'to_code' => 'G-N0001', 'step_free' => false]);

        $listed = $this->getJson(route('admin.maps.graph', $this->map))->json('edits.0');
        $this->assertSame(['move_node', 'G-N0001', ['x' => 10, 'y' => 2]], [$listed['action'], $listed['node_code'], $listed['data']]);
        $this->patchJson($listed['update_url'], ['x' => 10, 'y' => 4])
            ->assertOk()->assertJsonPath('data.key', $move->key)->assertJsonPath('data.description', 'Move node G-N0001 to x 10.00, y 4.00');
        $this->patchJson($listed['update_url'], ['node_code' => 'G-N9999'])->assertUnprocessable()->assertJsonValidationErrors('graph');
        $this->patchJson($listed['update_url'], ['x' => 'east'])->assertUnprocessable()->assertJsonValidationErrors('x');

        // The page form sends every field; "(unchanged)" leaves that setting alone.
        $this->patch(route('admin.maps.graph.update', [$this->map, $walkway]), ['from_code' => 'G-C01', 'to_code' => 'G-N0001', 'direction' => 'forward', 'step_free' => ''])
            ->assertRedirect()->assertSessionHas('status', 'Change the walkway G-C01 – G-N0001: one way G-C01 → G-N0001 — saved.');
        $this->assertSame(['direction' => 'forward'], $walkway->refresh()->data);
        $this->patch(route('admin.maps.graph.update', [$this->map, $walkway]), ['from_code' => 'G-C01', 'to_code' => 'G-N0001', 'direction' => '', 'step_free' => ''])
            ->assertSessionHasErrors('graph');

        $this->get(route('admin.maps.end-points', $this->map))->assertOk()
            ->assertSee('Save change')->assertSee('value="4"', false)->assertSee(route('admin.maps.graph.update', [$this->map, $walkway]), false);

        $other = $this->pilotMap('other-mall');
        $this->patchJson(route('admin.maps.graph.update', [$other, $walkway]), ['direction' => 'both'])->assertNotFound();
    }

    public function test_the_graph_json_shows_nodes_walkways_and_changes_and_takes_new_ones(): void
    {
        $this->actingAs(User::factory()->create());

        $graph = $this->getJson(route('admin.maps.graph', $this->map))->assertOk();
        $this->assertCount(19, $graph->json('nodes'));
        $this->assertContains(['code' => 'E-G-101', 'floor' => 'G', 'type' => 'entrance', 'x' => 20, 'y' => 3, 'z' => 0, 'added' => false, 'holds' => 'is a shop entrance'], $graph->json('nodes'));
        $escalator = collect($graph->json('edges'))->firstWhere('to', 'G-X-East-Escalator');
        $this->assertNotNull($escalator);
        $lift = collect($graph->json('edges'))->first(fn (array $edge): bool => $edge['connector'] && str_contains($edge['from'].$edge['to'], 'Central-Lift'));
        $this->assertSame('both', $lift['direction']);
        $up = collect($graph->json('edges'))->first(fn (array $edge): bool => $edge['connector'] && str_contains($edge['from'].$edge['to'], 'East-Escalator'));
        $this->assertNotSame('both', $up['direction']);

        $created = $this->postJson(route('admin.maps.graph.store', $this->map), ['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003', 'step_free' => true, 'one_way' => false])
            ->assertCreated()->assertJsonPath('data.description', 'Connect G-C01 and G-N0003')->json('data');
        $this->postJson(route('admin.maps.graph.store', $this->map), ['action' => 'remove_node', 'node_code' => 'E-G-101'])
            ->assertUnprocessable()->assertJsonValidationErrors('graph');
        $this->postJson(route('admin.maps.graph.store', $this->map), ['action' => 'move_node', 'node_code' => 'G-N0001'])
            ->assertUnprocessable()->assertJsonValidationErrors(['x', 'y']);
        $this->postJson(route('admin.maps.graph.store', $this->map), ['action' => 'teleport'])
            ->assertUnprocessable()->assertJsonValidationErrors('action');

        $this->assertSame('Connect G-C01 and G-N0003', $this->getJson(route('admin.maps.graph', $this->map))->json('edits.0.description'));
        $this->deleteJson($created['undo_url'])->assertNoContent();
        $this->assertSame(0, MapGraphEdit::query()->count());
    }

    public function test_guests_cannot_see_or_change_the_graph(): void
    {
        $edit = $this->change(['action' => 'connect', 'from_code' => 'G-C01', 'to_code' => 'G-N0003']);

        $this->get(route('admin.maps.graph', $this->map))->assertRedirect(route('login'));
        $this->post(route('admin.maps.graph.store', $this->map), ['action' => 'remove_edge', 'from_code' => 'G-C01', 'to_code' => 'G-N0001'])->assertRedirect(route('login'));
        $this->patch(route('admin.maps.graph.update', [$this->map, $edit]), ['to_code' => 'G-N0002'])->assertRedirect(route('login'));
        $this->delete(route('admin.maps.graph.destroy', [$this->map, $edit]))->assertRedirect(route('login'));
        $this->assertSame(1, MapGraphEdit::query()->count());
        $this->assertSame('G-N0003', $edit->refresh()->to_code);
    }

    public function test_the_page_form_changes_the_graph_and_lists_the_change_with_undo(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.maps.graph.store', $this->map), ['action' => 'remove_edge', 'from_code' => 'G-N0001', 'to_code' => 'G-N0002', 'direction' => '', 'step_free' => '', 'one_way' => '0'])
            ->assertRedirect()->assertSessionHas('status');
        $this->post(route('admin.maps.graph.store', $this->map), ['action' => 'edit_edge', 'from_code' => 'G-C01', 'to_code' => 'G-N0001', 'direction' => '', 'step_free' => ''])
            ->assertSessionHasErrors('graph');

        $edit = MapGraphEdit::query()->sole();
        $this->get(route('admin.maps.end-points', $this->map))->assertOk()
            ->assertSee('Navigation graph')->assertSee($edit->key)->assertSee('Remove the walkway G-N0001 – G-N0002');

        $this->delete(route('admin.maps.graph.destroy', [$this->map, $edit]))->assertRedirect()->assertSessionHas('status');
        $this->assertSame(20.0, $this->route('node:G-C01', 'node:G-N0002')['total']['length_m']);
    }
}
