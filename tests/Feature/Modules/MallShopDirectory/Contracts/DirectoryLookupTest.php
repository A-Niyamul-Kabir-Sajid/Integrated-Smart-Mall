<?php

namespace Tests\Feature\Modules\MallShopDirectory\Contracts;

use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The promises ProductsCatalogue, MapsNavigation and the workspaces rely on.
 */
class DirectoryLookupTest extends TestCase
{
    use RefreshDatabase;

    private function lookup(): DirectoryLookup
    {
        return app(DirectoryLookup::class);
    }

    public function test_resolves_only_active_malls_by_slug(): void
    {
        $active = Mall::factory()->create(['slug' => 'open-mall']);
        Mall::factory()->draft()->create(['slug' => 'draft-mall']);
        Mall::factory()->archived()->create(['slug' => 'old-mall']);

        $this->assertTrue($this->lookup()->activeMallBySlug('open-mall')->is($active));
        $this->assertNull($this->lookup()->activeMallBySlug('draft-mall'));
        $this->assertNull($this->lookup()->activeMallBySlug('old-mall'));
        $this->assertNull($this->lookup()->activeMallBySlug('missing'));
    }

    public function test_workspaces_can_load_a_shop_in_any_status(): void
    {
        $draft = Shop::factory()->draft()->create();

        $this->assertTrue($this->lookup()->shop($draft->getKey())->is($draft));
        $this->assertNull($this->lookup()->shop((string) Str::uuid()));
    }

    public function test_only_active_shops_are_operational_for_products(): void
    {
        $this->assertTrue($this->lookup()->isShopOperational(Shop::factory()->create()->getKey()));

        foreach (['draft', 'closed', 'archived'] as $status) {
            $this->assertFalse($this->lookup()->isShopOperational(Shop::factory()->create(['status' => $status])->getKey()), "{$status} shops must not sell.");
        }

        $this->assertFalse($this->lookup()->isShopOperational((string) Str::uuid()));
    }

    public function test_listed_shop_ids_are_the_malls_visible_shops(): void
    {
        $mall = Mall::factory()->create();
        $active = Shop::factory()->for($mall)->create();
        $closed = Shop::factory()->for($mall)->closed()->create();
        Shop::factory()->for($mall)->draft()->create();
        Shop::factory()->create();

        $this->assertEqualsCanonicalizing([$active->getKey(), $closed->getKey()], $this->lookup()->listedShopIds($mall->getKey()));
    }

    public function test_floors_are_the_malls_active_floors_in_display_order(): void
    {
        $mall = Mall::factory()->create();
        Floor::factory()->for($mall)->create(['code' => 'F1', 'sort_order' => 1]);
        Floor::factory()->for($mall)->create(['code' => 'G', 'sort_order' => 0]);
        Floor::factory()->for($mall)->create(['code' => 'X', 'sort_order' => 5, 'status' => 'archived']);
        Floor::factory()->create(['code' => 'B1', 'sort_order' => -1]);

        $this->assertSame(['G', 'F1'], $this->lookup()->floorsOf($mall->getKey())->pluck('code')->all());
    }

    public function test_locations_are_the_shops_current_units_with_their_floors(): void
    {
        $shop = Shop::factory()->create();
        $floor = Floor::factory()->for($shop->mall)->create(['code' => 'F1']);
        ShopUnit::factory()->for($shop)->for($floor)->create(['unit_code' => 'F1-06']);
        ShopUnit::factory()->for($shop)->for($floor)->create(['unit_code' => 'F1-05']);
        ShopUnit::factory()->for($shop)->for($floor)->create(['unit_code' => 'F1-09', 'status' => 'archived']);

        $locations = $this->lookup()->locationsOf($shop->getKey());

        $this->assertSame(['F1-05', 'F1-06'], $locations->pluck('unit_code')->all());
        $this->assertTrue($locations->first()->relationLoaded('floor'));
        $this->assertSame('F1', $locations->first()->floor->code);
    }
}
