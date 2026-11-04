<?php

namespace Tests\Feature\Modules\MallShopDirectory\Models;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FloorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mall_lists_its_floors_in_display_order(): void
    {
        $mall = Mall::factory()->create();
        Floor::factory()->for($mall)->create(['code' => 'F2', 'sort_order' => 2]);
        Floor::factory()->for($mall)->create(['code' => 'G', 'sort_order' => 0]);
        Floor::factory()->for($mall)->create(['code' => 'F1', 'sort_order' => 1]);
        Floor::factory()->create(['code' => 'B1', 'sort_order' => -1]);

        $this->assertSame(['G', 'F1', 'F2'], $mall->floors()->pluck('code')->all());
    }

    public function test_a_floor_code_is_unique_within_its_mall_only(): void
    {
        $first = Mall::factory()->create();
        Floor::factory()->for($first)->create(['code' => 'G']);
        Floor::factory()->for(Mall::factory())->create(['code' => 'G']);

        $this->expectException(QueryException::class);

        Floor::factory()->for($first)->create(['code' => 'G']);
    }

    public function test_a_floor_must_reference_an_existing_mall(): void
    {
        $this->expectException(QueryException::class);

        Floor::factory()->create(['mall_id' => (string) Str::uuid()]);
    }

    public function test_a_mall_with_floors_cannot_be_deleted(): void
    {
        $floor = Floor::factory()->create();

        $this->expectException(QueryException::class);

        $floor->mall->delete();
    }
}
