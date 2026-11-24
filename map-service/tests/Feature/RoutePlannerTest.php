<?php

namespace Tests\Feature;

use App\Exceptions\RouteNotPossible;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Services\EdgeStatusService;
use App\Services\Routing\RoutePlanner;
use Database\Factories\MallMapFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ISM-62: the route engine on the two-floor pilot mall (costs worked out in
 * MallMapFactory::pilotPackage()).
 */
class RoutePlannerTest extends TestCase
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
     * @param  list<string>|string  $to
     * @return array<string, mixed>
     */
    private function route(string $from, array|string $to, bool $stepFree = false, string $cost = 'time'): array
    {
        return app(RoutePlanner::class)->plan($this->map, $from, (array) $to, $stepFree, $cost);
    }

    /**
     * @param  array<string, mixed>  $route
     * @return list<string>
     */
    private function codes(array $route): array
    {
        return array_column($route['path'], 'node_code');
    }

    private function slug(string $code): string
    {
        return Checkpoint::query()->where('checkpoint_code', $code)->value('public_slug');
    }

    public function test_the_fastest_route_upstairs_takes_the_escalator(): void
    {
        $route = $this->route('checkpoint:'.$this->slug('G-C01'), 'unit:1-205');

        $this->assertSame(['G-C01', 'G-N0001', 'G-X-East-Escalator', '1-X-East-Escalator', '1-N0002', 'E-1-205'], $this->codes($route));
        $this->assertSame(29.8, $route['total']['length_m']);
        $this->assertSame(29.0, $route['total']['seconds']);
        $this->assertSame(1, $route['total']['minutes']);
        $this->assertSame(['G', '1'], $route['floors']);
        $this->assertSame('Main entrance, pillar facing the doors', $route['from']['label']);
        $this->assertContains('Take East Escalator up to First floor.', array_column($route['steps'], 'instruction'));
        $this->assertSame('Arrive at Shop 1-205.', end($route['steps'])['instruction']);
    }

    public function test_a_step_free_route_takes_the_lift(): void
    {
        $route = $this->route('node:G-C01', 'unit:1-205', stepFree: true);

        $this->assertSame(['G-C01', 'G-X-Central-Lift', '1-X-Central-Lift', '1-C01', '1-N0001', '1-N0002', 'E-1-205'], $this->codes($route));
        $this->assertSame(37.0, $route['total']['length_m']);
        $this->assertSame(61.7, $route['total']['seconds']);
        $this->assertTrue($route['step_free']);
    }

    public function test_a_closure_makes_the_route_go_another_way_at_once(): void
    {
        app(EdgeStatusService::class)->set($this->map, 'G-X-East-Escalator', '1-X-East-Escalator', 'closed', 'Repairs', bothWays: false);

        $fastest = $this->route('node:G-C01', 'unit:1-205');
        $this->assertContains('G-X-Main-Stairs', $this->codes($fastest));
        $this->assertSame(47.5, $fastest['total']['seconds']);
        $this->assertSame(50.0, $fastest['total']['length_m']);

        $shortest = $this->route('node:G-C01', 'unit:1-205', cost: 'distance');
        $this->assertContains('G-X-Central-Lift', $this->codes($shortest));
        $this->assertSame(37.0, $shortest['total']['length_m']);

        app(EdgeStatusService::class)->set($this->map, 'G-X-East-Escalator', '1-X-East-Escalator', 'open', bothWays: false);
        $this->assertContains('G-X-East-Escalator', $this->codes($this->route('node:G-C01', 'unit:1-205')));
    }

    public function test_an_up_escalator_is_never_used_going_down_and_the_nearest_door_is_chosen(): void
    {
        $fastest = $this->route('checkpoint:'.$this->slug('1-C01'), 'unit:G-101');
        $this->assertNotContains('1-X-East-Escalator', $this->codes($fastest));
        $this->assertSame(['1-C01', '1-N0001', '1-N0002', '1-X-Main-Stairs', 'G-X-Main-Stairs', 'G-N0002', 'E-G-101'], $this->codes($fastest));
        $this->assertSame('Take Main Stairs down to Ground floor.', $fastest['steps'][3]['instruction']);

        $stepFree = $this->route('checkpoint:'.$this->slug('1-C01'), 'unit:G-101', stepFree: true);
        $this->assertSame(['1-C01', '1-X-Central-Lift', 'G-X-Central-Lift', 'G-N0003', 'E-G-101-B'], $this->codes($stepFree));
    }

    public function test_the_nearest_facility_of_a_type_is_found(): void
    {
        $this->assertSame('1-F-Toilets-1', $this->route('node:1-C01', 'facility-type:toilet')['to'][0]['node_code']);
        $this->assertSame('G-F-Toilets-G', $this->route('node:G-C01', 'facility-type:toilet')['to'][0]['node_code']);
        $this->assertSame('the nearest toilet', $this->route('node:G-C01', 'facility-type:toilet')['to'][0]['label']);
    }

    public function test_turns_are_written_as_steps(): void
    {
        $route = $this->route('node:G-C01', 'facility:Toilets G');

        $this->assertSame(['G-C01', 'G-N0001', 'G-N0003', 'G-F-Toilets-G'], $this->codes($route));
        $this->assertSame(['Start at G-C01.', 'Walk 10 m.', 'Turn left and walk 11 m.', 'Arrive at Toilets G.'], array_column($route['steps'], 'instruction'));
        $this->assertSame([0.0, 10.0, 11.0, 0.0], array_column($route['steps'], 'length_m'));
    }

    public function test_several_stops_are_visited_in_order(): void
    {
        $route = $this->route('node:G-C01', ['unit:G-101', 'facility:Prayer room']);

        $this->assertCount(2, $route['legs']);
        // The side door of G-101 (17.5 s) is nearer than its main door (19.2 s).
        $this->assertSame('E-G-101-B', $route['legs'][0]['node_code']);
        $this->assertSame('1-F-Prayer-Room', $route['legs'][1]['node_code']);
        $types = array_column($route['steps'], 'type');
        $this->assertSame('stop', $types[array_search('Stop at Shop G-101.', array_column($route['steps'], 'instruction'), true)]);
        $this->assertSame('arrive', end($types));
        $this->assertEqualsWithDelta($route['total']['seconds'], $route['legs'][0]['seconds'] + $route['legs'][1]['seconds'], 0.11);
        $this->assertSame('E-G-101-B', $route['path'][$route['legs'][1]['from_index']]['node_code']);
    }

    public function test_the_same_question_always_gets_the_same_answer(): void
    {
        $first = $this->route('node:1-C01', 'unit:G-101');
        $second = $this->route('node:1-C01', 'unit:G-101');

        $this->assertSame($this->codes($first), $this->codes($second));
        $this->assertSame($first['steps'], $second['steps']);
    }

    public function test_starting_at_the_destination_says_so(): void
    {
        $route = $this->route('node:E-G-101', 'unit:G-101');

        $this->assertSame(['E-G-101'], $this->codes($route));
        $this->assertSame('You are already there.', end($route['steps'])['instruction']);
    }

    public function test_no_step_free_route_when_the_lift_is_closed(): void
    {
        app(EdgeStatusService::class)->set($this->map, 'G-X-Central-Lift', '1-X-Central-Lift', 'closed', 'Broken');

        try {
            $this->route('node:G-C01', 'unit:1-205', stepFree: true);
            $this->fail('Expected no route.');
        } catch (RouteNotPossible $error) {
            $this->assertSame('unreachable', $error->reason);
            $this->assertSame('to', $error->field);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function unknownPlaces(): array
    {
        return [
            'no kind' => ['G-C01', 'unit:G-101', 'from'],
            'unknown kind' => ['room:G-C01', 'unit:G-101', 'from'],
            'unknown node' => ['node:G-N9999', 'unit:G-101', 'from'],
            'unknown unit' => ['node:G-C01', 'unit:G-999', 'to'],
            'unknown facility type' => ['node:G-C01', 'facility-type:cinema', 'to'],
            'unknown checkpoint' => ['checkpoint:nosuchslug12', 'unit:G-101', 'from'],
        ];
    }

    #[DataProvider('unknownPlaces')]
    public function test_unknown_places_are_refused_and_named(string $from, string $to, string $field): void
    {
        try {
            $this->route($from, $to);
            $this->fail('Expected a refusal.');
        } catch (RouteNotPossible $error) {
            $this->assertSame('unknown_place', $error->reason);
            $this->assertSame($field, $error->field);
        }
    }

    public function test_a_checkpoint_of_another_mall_or_a_retired_one_is_not_a_start(): void
    {
        $other = $this->pilotMap('other-mall');
        $otherSlug = Checkpoint::query()->where('mall_ref', 'other-mall')->where('checkpoint_code', 'G-C01')->value('public_slug');
        $this->assertSame('E-G-101-B', app(RoutePlanner::class)->plan($other, "checkpoint:{$otherSlug}", ['unit:G-101'])['to'][0]['node_code']);

        $this->expectException(RouteNotPossible::class);
        $this->route("checkpoint:{$otherSlug}", 'unit:G-101');
    }

    public function test_a_retired_checkpoint_is_not_a_start(): void
    {
        $slug = $this->slug('G-C01');
        Checkpoint::query()->where('public_slug', $slug)->update(['status' => 'retired']);

        $this->expectException(RouteNotPossible::class);
        $this->route("checkpoint:{$slug}", 'unit:G-101');
    }

    public function test_small_jogs_are_folded_and_a_connector_named_up_is_not_said_twice(): void
    {
        $package = MallMapFactory::pilotPackage();
        // A 1.1 m jog at the corner: (10,0) -> (10.5,1) -> (10,9).
        $package['nodes'][] = ['node_code' => 'G-N0004', 'floor_code' => 'G', 'node_type' => 'junction', 'x_m' => 10.5, 'y_m' => 1.0, 'z_m' => 0.0, 'is_accessible' => true];
        foreach ($package['edges'] as $index => $edge) {
            if (in_array([$edge['from_node_code'], $edge['to_node_code']], [['G-N0001', 'G-N0003'], ['G-N0003', 'G-N0001']], true)) {
                unset($package['edges'][$index]);
            }
        }
        foreach ([['G-N0001', 'G-N0004', 1.118], ['G-N0004', 'G-N0003', 8.016]] as [$a, $b, $length]) {
            foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
                $package['edges'][] = ['from_node_code' => $from, 'to_node_code' => $to, 'length_m' => $length, 'expected_seconds' => round($length / 1.2, 2), 'is_accessible' => true, 'status' => 'open', 'connector' => null];
            }
        }
        $package['edges'] = array_values($package['edges']);
        $package['connectors'][2]['name'] = 'East Escalator Up';
        foreach ($package['edges'] as $index => $edge) {
            if ($edge['connector'] === 'East Escalator') {
                $package['edges'][$index]['connector'] = 'East Escalator Up';
            }
        }
        $this->map = $this->pilotMap('jog-mall', overrides: ['nodes' => $package['nodes'], 'edges' => $package['edges'], 'connectors' => $package['connectors']]);

        $toToilets = $this->route('node:G-C01', 'facility:Toilets G');
        $this->assertSame(['G-C01', 'G-N0001', 'G-N0004', 'G-N0003', 'G-F-Toilets-G'], $this->codes($toToilets));
        $this->assertSame(['Start at G-C01.', 'Walk 10 m.', 'Turn left and walk 11 m.', 'Arrive at Toilets G.'], array_column($toToilets['steps'], 'instruction'));
        $this->assertSame('G-N0001', $toToilets['steps'][2]['node_code']);

        $upstairs = $this->route('node:G-C01', 'unit:1-205');
        $this->assertContains('Take East Escalator Up to First floor.', array_column($upstairs['steps'], 'instruction'));
    }
}
