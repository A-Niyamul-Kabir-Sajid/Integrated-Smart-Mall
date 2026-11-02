<?php

namespace Tests\Feature\Modules\MallShopDirectory\Models;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MallTest extends TestCase
{
    use RefreshDatabase;

    public function test_malls_are_keyed_by_uuid_and_record_their_creation_time(): void
    {
        $mall = Mall::factory()->create();

        $this->assertTrue(Str::isUuid($mall->getKey()));
        $this->assertNotNull($mall->fresh()->created_at);
        $this->assertSame(MallStatus::Active, $mall->fresh()->status);
    }

    public function test_slugs_are_unique(): void
    {
        Mall::factory()->create(['slug' => 'demo-city-mall']);

        $this->expectException(QueryException::class);

        Mall::factory()->create(['slug' => 'demo-city-mall']);
    }

    public function test_active_scope_excludes_draft_and_archived_malls(): void
    {
        $active = Mall::factory()->create();
        Mall::factory()->draft()->create();
        Mall::factory()->archived()->create();

        $this->assertSame([$active->getKey()], Mall::query()->active()->pluck('mall_id')->all());
    }
}
