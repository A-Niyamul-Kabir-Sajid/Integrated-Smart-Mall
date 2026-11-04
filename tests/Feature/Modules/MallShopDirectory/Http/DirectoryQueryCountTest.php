<?php

namespace Tests\Feature\Modules\MallShopDirectory\Http;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards against N+1 queries: the number of queries a directory page runs must
 * not grow with the number of shops on it.
 */
class DirectoryQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function mallWithShops(int $count): Mall
    {
        $mall = Mall::factory()->create();
        $floor = Floor::factory()->for($mall)->create();

        foreach (range(1, $count) as $number) {
            $shop = Shop::factory()->for($mall)->create();
            ShopUnit::factory()->for($shop)->for($floor)->create(['unit_code' => "U-{$number}"]);
            app(ShopService::class)->setOpeningHours($shop, [['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '20:00']]);
        }

        return $mall;
    }

    private function queriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    public function test_directory_and_mall_home_query_counts_do_not_grow_with_the_number_of_shops(): void
    {
        $few = $this->mallWithShops(2);
        $many = $this->mallWithShops(12);

        foreach (['shops.index', 'malls.show'] as $route) {
            $this->assertSame(
                $this->queriesFor(route($route, ['publicMall' => $few->slug, 'open_now' => 1])),
                $this->queriesFor(route($route, ['publicMall' => $many->slug, 'open_now' => 1])),
                "{$route} runs more queries for more shops.",
            );
        }
    }
}
