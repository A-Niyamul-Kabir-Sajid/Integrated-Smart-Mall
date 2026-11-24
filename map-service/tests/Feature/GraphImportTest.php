<?php

namespace Tests\Feature;

use App\Models\MallMap;
use App\Models\MapEdge;
use App\Models\MapNode;
use App\Models\User;
use App\Services\GraphBuilder;
use App\Services\MapPublisher;
use App\Services\PackageImporter;
use Database\Factories\MallMapFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ISM-59: the package's navigation graph is checked and stored in the map_*
 * tables, one set per map, and a mall has at most one published map.
 */
class GraphImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_importing_writes_every_graph_record_of_the_package(): void
    {
        $map = $this->pilotMap();
        $package = MallMapFactory::pilotPackage();

        $this->assertSame('demo-mall', $map->mall_ref);
        $this->assertSame(['G', '1'], $map->floors()->pluck('floor_code')->all());
        $this->assertSame(count($package['nodes']), $map->nodes()->count());
        $this->assertSame(count($package['edges']), $map->edges()->count());
        $this->assertSame(3, $map->connectors()->count());
        $this->assertSame(6, DB::table('map_connector_stops')->where('mall_map_id', $map->id)->count());
        $this->assertSame(3, $map->shopEntrances()->count());
        $this->assertSame(3, $map->facilityAnchors()->count());
        $this->assertSame(['1-C01', 'G-C01'], $map->checkpointAnchors()->orderBy('checkpoint_code')->pluck('checkpoint_code')->all());

        $escalator = MapEdge::query()->where('mall_map_id', $map->id)->whereNotNull('map_connector_id')->whereHas('connector', fn ($query) => $query->where('name', 'East Escalator'))->sole();
        $this->assertSame('G-X-East-Escalator', $escalator->fromNode->node_code);
        $this->assertSame('1-X-East-Escalator', $escalator->toNode->node_code);
        $this->assertFalse($escalator->is_accessible);
        $this->assertEquals(['x_m' => 5.0, 'y_m' => 10.8, 'z_m' => 6.4, 'yaw_deg' => 270.0, 'marker_height_m' => 1.4, 'physical_width_m' => 0.18], $map->checkpointAnchors()->where('checkpoint_code', '1-C01')->sole()->qr);
    }

    public function test_two_maps_keep_separate_graphs_with_the_same_codes(): void
    {
        $first = $this->pilotMap('mall-a');
        $second = $this->pilotMap('mall-b');

        $this->assertSame(19, $first->nodes()->count());
        $this->assertSame(19, $second->nodes()->count());
        $this->assertNotEquals(
            $first->nodes()->where('node_code', 'G-C01')->value('id'),
            $second->nodes()->where('node_code', 'G-C01')->value('id'),
        );
    }

    public function test_the_database_refuses_an_edge_whose_node_belongs_to_another_map(): void
    {
        $first = $this->pilotMap('mall-a');
        $second = $this->pilotMap('mall-b');

        $this->expectException(QueryException::class);
        MapEdge::query()->create([
            'mall_map_id' => $first->id,
            'from_node_id' => $first->nodes()->where('node_code', 'G-C01')->value('id'),
            'to_node_id' => $second->nodes()->where('node_code', 'G-N0001')->value('id'),
            'length_m' => 1,
            'expected_seconds' => 1,
        ]);
    }

    public function test_deleting_a_map_removes_its_graph(): void
    {
        $map = $this->pilotMap();
        $map->delete();

        $this->assertSame(0, MapNode::query()->count());
        $this->assertSame(0, MapEdge::query()->count());
        $this->assertSame(0, DB::table('map_checkpoint_anchors')->count());
    }

    /**
     * @return array<string, array{0: callable(array<string, mixed>): array<string, mixed>, 1: string}>
     */
    public static function brokenGraphs(): array
    {
        return [
            'edge to an unknown node' => [function (array $p): array {
                $p['edges'][0]['to_node_code'] = 'G-N9999';

                return $p;
            }, 'joins an unknown node'],
            'edge to itself' => [function (array $p): array {
                $p['edges'][0]['to_node_code'] = $p['edges'][0]['from_node_code'];

                return $p;
            }, 'starts and ends at the same node'],
            'zero length' => [function (array $p): array {
                $p['edges'][0]['length_m'] = 0;

                return $p;
            }, 'needs a length_m above 0'],
            'duplicate node' => [function (array $p): array {
                $p['nodes'][] = $p['nodes'][0];

                return $p;
            }, 'Node G-C01 appears twice'],
            'node on an unknown floor' => [function (array $p): array {
                $p['nodes'][0]['floor_code'] = '9';

                return $p;
            }, 'is on an unknown floor'],
            'floor change without a connector' => [function (array $p): array {
                $p['edges'][] = ['from_node_code' => 'G-C01', 'to_node_code' => '1-C01', 'length_m' => 5, 'expected_seconds' => 5, 'is_accessible' => true, 'status' => 'open', 'connector' => null];

                return $p;
            }, 'changes floor without a connector'],
            'floor change outside the connector stops' => [function (array $p): array {
                $p['edges'][] = ['from_node_code' => 'G-C01', 'to_node_code' => '1-C01', 'length_m' => 5, 'expected_seconds' => 5, 'is_accessible' => true, 'status' => 'open', 'connector' => 'Central Lift'];

                return $p;
            }, 'its ends are not stops of Central Lift'],
            'escalator both ways' => [function (array $p): array {
                $p['connectors'][2]['direction'] = 'both';

                return $p;
            }, 'Escalator East Escalator cannot run both ways'],
            'connector on one floor' => [function (array $p): array {
                $p['connectors'][0]['stops'] = [$p['connectors'][0]['stops'][0]];

                return $p;
            }, 'needs stops on at least two floors'],
            'two primary entrances' => [function (array $p): array {
                $p['shop_entrances'][1]['is_primary'] = true;

                return $p;
            }, 'Unit G-101 has more than one primary entrance'],
            'entrance on the wrong floor' => [function (array $p): array {
                $p['shop_entrances'][0]['floor_code'] = '1';

                return $p;
            }, 'is not on floor 1'],
            'checkpoint without a pose' => [function (array $p): array {
                unset($p['checkpoints'][0]['qr']['yaw_deg']);

                return $p;
            }, 'has no qr.yaw_deg'],
            'duplicate checkpoint' => [function (array $p): array {
                $p['checkpoints'][1]['checkpoint_code'] = 'G-C01';

                return $p;
            }, 'QR checkpoint G-C01 appears twice'],
        ];
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $break
     */
    #[DataProvider('brokenGraphs')]
    public function test_a_broken_graph_is_refused_and_nothing_is_stored(callable $break, string $message): void
    {
        $folder = $this->packageFolder(base: $break(MallMapFactory::pilotPackage()));

        try {
            app(PackageImporter::class)->import($folder, 'Broken', true, 'demo-mall');
            $this->fail('The import should have been refused.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }

        $this->assertSame(0, MallMap::query()->count());
        $this->assertSame(0, MapNode::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_publishing_a_map_for_a_mall_unpublishes_its_previous_map(): void
    {
        $old = $this->pilotMap('demo-mall');
        $new = $this->pilotMap('demo-mall', publish: false);
        $other = $this->pilotMap('other-mall');

        app(MapPublisher::class)->publish($new);

        $this->assertSame('draft', $old->refresh()->status);
        $this->assertSame('published', $new->refresh()->status);
        $this->assertSame('published', $other->refresh()->status);
        $this->assertTrue(MallMap::publishedFor('demo-mall')->is($new));
    }

    public function test_the_database_refuses_two_published_maps_for_one_mall(): void
    {
        $this->pilotMap('demo-mall');
        $second = $this->pilotMap('demo-mall', publish: false);

        $this->expectException(QueryException::class);
        DB::table('mall_maps')->where('id', $second->id)->update(['status' => 'published']);
    }

    public function test_an_invalid_mall_slug_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pilotMap('Demo Mall!');
    }

    public function test_the_rebuild_command_restores_a_graph_from_the_stored_package(): void
    {
        $map = $this->pilotMap();
        $map->edges()->update(['status' => 'closed']);
        DB::table('map_nodes')->where('mall_map_id', $map->id)->where('node_code', 'G-C01')->update(['x_m' => 99]);

        $this->artisan('map:rebuild-graph', ['map' => $map->id])->assertSuccessful();

        $this->assertSame(0, $map->edges()->where('status', 'closed')->count());
        $this->assertSame(0.0, $map->nodes()->where('node_code', 'G-C01')->sole()->x_m);
        $this->artisan('map:rebuild-graph', ['map' => 'no-such-map'])->assertFailed();
    }

    public function test_the_import_command_takes_the_mall(): void
    {
        $folder = $this->packageFolder(base: MallMapFactory::pilotPackage());

        $this->artisan('map:import', ['folder' => $folder, '--name' => 'Pilot', '--mall' => 'demo-mall', '--publish' => true])
            ->expectsOutputToContain('graph: 19 nodes')
            ->assertSuccessful();

        $this->assertSame('demo-mall', MallMap::query()->sole()->mall_ref);
    }

    public function test_an_administrator_links_a_map_to_a_mall_and_publication_moves_with_it(): void
    {
        $admin = User::factory()->create();
        $old = $this->pilotMap('demo-mall');
        $map = $this->pilotMap('other-mall');

        $this->actingAs($admin)->patch(route('admin.maps.mall', $map), ['mall_ref' => 'demo-mall'])->assertRedirect();

        $this->assertSame('demo-mall', $map->refresh()->mall_ref);
        $this->assertSame('published', $map->status);
        $this->assertSame('draft', $old->refresh()->status);

        $this->actingAs($admin)->patch(route('admin.maps.mall', $map), ['mall_ref' => 'Not A Slug'])->assertSessionHasErrors('mall_ref');
        $this->patch(route('admin.maps.mall', $map), ['mall_ref' => ''])->assertRedirect();
        $this->assertNull($map->refresh()->mall_ref);
    }

    public function test_guests_cannot_change_the_mall_of_a_map(): void
    {
        $map = $this->pilotMap();

        auth()->logout();
        $this->patch(route('admin.maps.mall', $map), ['mall_ref' => 'other'])->assertRedirect(route('login'));
        $this->assertSame('demo-mall', $map->refresh()->mall_ref);
    }

    public function test_graph_problems_are_listed_without_writing(): void
    {
        $this->assertSame([], app(GraphBuilder::class)->problems(MallMapFactory::pilotPackage()));
        $this->assertSame([], app(GraphBuilder::class)->problems(MallMapFactory::package()));
    }
}
