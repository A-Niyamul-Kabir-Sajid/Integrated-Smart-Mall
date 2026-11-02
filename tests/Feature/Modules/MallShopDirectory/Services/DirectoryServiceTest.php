<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\DirectoryService;
use App\Modules\MallShopDirectory\Services\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class DirectoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);
    }

    private function shop(string $name, array $attributes = []): Shop
    {
        return Shop::factory()->for($this->mall)->create(['name' => $name, ...$attributes]);
    }

    private function names(array $filters = [], int $perPage = 12, int $page = 1): array
    {
        return app(DirectoryService::class)->browseShops($this->mall, $filters, $perPage)->pluck('name')->all();
    }

    public function test_lists_only_this_malls_active_and_closed_shops_alphabetically(): void
    {
        $this->shop('Zeta Shoes');
        $this->shop('Alpha Cafe');
        $this->shop('Closed Books', ['status' => 'closed']);
        $this->shop('Draft Store', ['status' => 'draft']);
        $this->shop('Old Store', ['status' => 'archived']);
        Shop::factory()->create(['name' => 'Other Mall Shop']);

        $this->assertSame(['Alpha Cafe', 'Closed Books', 'Zeta Shoes'], $this->names());
    }

    public function test_paginates_with_a_stable_order(): void
    {
        foreach (range(1, 5) as $number) {
            $this->shop("Shop {$number}");
        }

        $page = app(DirectoryService::class)->browseShops($this->mall, [], perPage: 2);

        $this->assertSame(5, $page->total());
        $this->assertSame(3, $page->lastPage());
        $this->assertSame(['Shop 1', 'Shop 2'], $page->pluck('name')->all());
    }

    public function test_filters_by_category_and_floor_together(): void
    {
        $food = ShopCategory::factory()->create(['slug' => 'food']);
        $ground = Floor::factory()->for($this->mall)->create(['code' => 'G']);
        $first = Floor::factory()->for($this->mall)->create(['code' => 'F1']);
        ShopUnit::factory()->for($this->shop('Ground Cafe', ['shop_category_id' => $food->getKey()]))->for($ground)->create();
        ShopUnit::factory()->for($this->shop('First Cafe', ['shop_category_id' => $food->getKey()]))->for($first)->create();
        ShopUnit::factory()->for($this->shop('Ground Phones'))->for($ground)->create();

        $this->assertSame(['Ground Cafe', 'Ground Phones'], $this->names(['floor' => 'G']));
        $this->assertSame(['First Cafe', 'Ground Cafe'], $this->names(['category' => 'food']));
        $this->assertSame(['Ground Cafe'], $this->names(['category' => 'food', 'floor' => 'G']));
    }

    public function test_open_now_filter_and_flag_use_the_mall_timezone(): void
    {
        Date::setTestNow(Date::parse('2026-11-02 12:00', 'Asia/Dhaka'));
        $service = app(ShopService::class);
        $service->setOpeningHours($this->shop('Lunch Place'), [['weekday' => 1, 'opens_at' => '11:00', 'closes_at' => '15:00']]);
        $service->setOpeningHours($this->shop('Night Bar'), [['weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '02:00', 'closes_next_day' => true]]);
        $this->shop('Closed Shop', ['status' => 'closed']);

        $this->assertSame(['Lunch Place'], $this->names(['open_now' => true]));

        $flags = app(DirectoryService::class)->browseShops($this->mall)->mapWithKeys(fn (Shop $shop) => [$shop->name => $shop->is_open_now])->all();
        $this->assertSame(['Closed Shop' => false, 'Lunch Place' => true, 'Night Bar' => false], $flags);
    }
}
