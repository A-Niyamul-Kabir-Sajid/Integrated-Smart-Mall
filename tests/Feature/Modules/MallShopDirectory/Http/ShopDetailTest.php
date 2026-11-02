<?php

namespace Tests\Feature\Modules\MallShopDirectory\Http;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ShopDetailTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['slug' => 'demo-city-mall', 'timezone' => 'Asia/Dhaka']);
    }

    private function url(string $shopSlug, ?Mall $mall = null): string
    {
        return route('shops.show', ['publicMall' => ($mall ?? $this->mall)->slug, 'publicShop' => $shopSlug]);
    }

    public function test_shows_profile_location_contact_and_this_weeks_hours(): void
    {
        Date::setTestNow(Date::parse('2026-11-02 12:00', 'Asia/Dhaka'));
        $shop = Shop::factory()->for($this->mall)->create([
            'slug' => 'food-court', 'name' => 'Food Court', 'description' => 'Lunch and dinner.',
            'contact_phone' => '+8801700000012', 'shop_category_id' => ShopCategory::factory()->create(['name' => 'Food & Drinks'])->getKey(),
        ]);
        $floor = Floor::factory()->for($this->mall)->create(['name' => 'First Floor']);
        ShopUnit::factory()->for($shop)->for($floor)->create(['unit_code' => 'F1-05']);
        ShopUnit::factory()->for($shop)->for($floor)->create(['unit_code' => 'F1-06']);
        app(ShopService::class)->setOpeningHours($shop, [
            ['weekday' => 1, 'opens_at' => '11:00', 'closes_at' => '15:00'],
            ['weekday' => 1, 'opens_at' => '17:00', 'closes_at' => '23:30'],
        ]);

        $this->get($this->url('food-court'))
            ->assertOk()
            ->assertSeeInOrder(['Food &amp; Drinks', 'Food Court', 'Open now', 'Lunch and dinner.'], false)
            ->assertSeeInOrder(['First Floor · Unit F1-05', 'First Floor · Unit F1-06'])
            ->assertSee('href="tel:+8801700000012"', false)
            ->assertSee('11:00 – 15:00, 17:00 – 23:30')
            ->assertSee('aria-current="date"', false)
            ->assertSee('Times are in Asia/Dhaka.')
            ->assertSee('data-integration="products"', false);
    }

    public function test_closed_shops_are_shown_as_closed_without_hours_or_location(): void
    {
        Shop::factory()->for($this->mall)->closed()->create(['slug' => 'sports-zone', 'contact_phone' => null]);

        $this->get($this->url('sports-zone'))
            ->assertOk()
            ->assertSee('Closed')
            ->assertSee('Location not published')
            ->assertDontSee('tel:', false);
    }

    public function test_drafts_unknown_slugs_and_shops_of_another_mall_are_not_found(): void
    {
        Shop::factory()->for($this->mall)->draft()->create(['slug' => 'tech-galaxy']);
        $otherMall = Mall::factory()->create();
        Shop::factory()->for($otherMall)->create(['slug' => 'elsewhere']);

        $this->get($this->url('tech-galaxy'))->assertNotFound();
        $this->get($this->url('no-such-shop'))->assertNotFound();
        $this->get($this->url('elsewhere'))->assertNotFound();
        $this->get($this->url('elsewhere', $otherMall))->assertOk();
    }

    public function test_directory_cards_link_to_the_detail_page(): void
    {
        Shop::factory()->for($this->mall)->create(['slug' => 'cafe-brew', 'name' => 'Cafe Brew']);

        $this->get(route('shops.index', ['publicMall' => $this->mall->slug]))
            ->assertSee('href="'.$this->url('cafe-brew').'"', false);
    }
}
