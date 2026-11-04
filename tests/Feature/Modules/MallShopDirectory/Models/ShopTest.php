<?php

namespace Tests\Feature\Modules\MallShopDirectory\Models;

use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_belongs_to_one_mall_and_category_and_occupies_units_on_floors(): void
    {
        $mall = Mall::factory()->create();
        $category = ShopCategory::factory()->create();
        $ground = Floor::factory()->for($mall)->create(['code' => 'G']);
        $first = Floor::factory()->for($mall)->create(['code' => 'F1']);
        $shop = Shop::factory()->for($mall)->for($category, 'category')->create();
        ShopUnit::factory()->for($shop)->for($ground)->create(['unit_code' => 'G-05']);
        ShopUnit::factory()->for($shop)->for($first)->create(['unit_code' => 'F1-05']);

        $this->assertTrue($shop->mall->is($mall));
        $this->assertTrue($shop->category->is($category));
        $this->assertEqualsCanonicalizing(['G-05', 'F1-05'], $shop->units()->pluck('unit_code')->all());
        $this->assertSame([$shop->getKey()], $mall->shops()->pluck('shop_id')->all());
        $this->assertSame([$shop->getKey()], $category->shops()->pluck('shop_id')->all());
        $this->assertSame(['G-05'], $ground->shopUnits()->pluck('unit_code')->all());
    }

    public function test_slugs_are_unique_within_a_mall_only(): void
    {
        $mall = Mall::factory()->create();
        Shop::factory()->for($mall)->create(['slug' => 'cafe-brew']);
        Shop::factory()->create(['slug' => 'cafe-brew']);

        $this->expectException(QueryException::class);

        Shop::factory()->for($mall)->create(['slug' => 'cafe-brew']);
    }

    public function test_unit_codes_are_unique_per_floor(): void
    {
        $floor = Floor::factory()->create();
        ShopUnit::factory()->for(Shop::factory()->for($floor->mall))->for($floor)->create(['unit_code' => 'G-01']);

        $this->expectException(QueryException::class);

        ShopUnit::factory()->for(Shop::factory()->for($floor->mall))->for($floor)->create(['unit_code' => 'G-01']);
    }

    public function test_listed_scope_returns_active_and_closed_shops_only(): void
    {
        $active = Shop::factory()->create();
        $closed = Shop::factory()->closed()->create();
        Shop::factory()->draft()->create();
        Shop::factory()->archived()->create();

        $this->assertEqualsCanonicalizing([$active->getKey(), $closed->getKey()], Shop::query()->listed()->pluck('shop_id')->all());
        $this->assertTrue(ShopStatus::Closed->isListed());
        $this->assertFalse(ShopStatus::Draft->isListed());
    }

    public function test_contact_phone_is_stored_in_e164(): void
    {
        $shop = Shop::factory()->create(['contact_phone' => '01700 000001']);

        $this->assertSame('+8801700000001', $shop->fresh()->contact_phone);
    }

    public function test_opening_hours_are_ordered_and_keyed_by_weekday_and_interval(): void
    {
        $shop = Shop::factory()->create();
        ShopOpeningHour::query()->insert([
            ['shop_id' => $shop->getKey(), 'weekday' => 2, 'interval_no' => 1, 'opens_at' => '10:00', 'closes_at' => '20:00', 'closes_next_day' => false],
            ['shop_id' => $shop->getKey(), 'weekday' => 1, 'interval_no' => 2, 'opens_at' => '17:00', 'closes_at' => '22:00', 'closes_next_day' => false],
            ['shop_id' => $shop->getKey(), 'weekday' => 1, 'interval_no' => 1, 'opens_at' => '10:00', 'closes_at' => '14:00', 'closes_next_day' => false],
        ]);

        $this->assertSame([[1, 1], [1, 2], [2, 1]], $shop->openingHours()->get()->map(fn (ShopOpeningHour $hour): array => [$hour->weekday, $hour->interval_no])->all());

        $this->expectException(QueryException::class);

        ShopOpeningHour::query()->insert(['shop_id' => $shop->getKey(), 'weekday' => 1, 'interval_no' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00', 'closes_next_day' => false]);
    }
}
