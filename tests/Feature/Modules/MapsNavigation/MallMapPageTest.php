<?php

namespace Tests\Feature\Modules\MapsNavigation;

use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ISM-64 in the Smart Mall app: the visitor's map page shows the map
 * service's embedded map, and QR codes land on it with their start point.
 */
class MallMapPageTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'ism_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN';

    private const EMBED = 'http://map-service.test/embed/maps/map-1?expires=1&signature=abc';

    private Mall $mall;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.map_service' => ['url' => 'http://map-service.test', 'key' => self::KEY, 'timeout' => 5]]);
        $this->mall = Mall::factory()->create(['slug' => 'demo-mall', 'name' => 'Demo Mall']);
    }

    private function fakeEmbed(): void
    {
        Http::fake(['map-service.test/api/v1/embed-links' => Http::response(['data' => ['url' => self::EMBED, 'expires_at' => now()->addMinutes(15)->toIso8601String(), 'map_id' => 'map-1', 'mall_ref' => 'demo-mall']], 201)]);
    }

    public function test_the_map_page_embeds_a_signed_link_without_the_key(): void
    {
        $this->fakeEmbed();

        $this->get(route('maps.show', ['publicMall' => 'demo-mall', 'to' => 'unit:G-101', 'step_free' => 1]))
            ->assertOk()
            ->assertSee('<iframe id="mall-map" src="'.e(self::EMBED).'"', false)
            ->assertSee('"http:\/\/map-service.test"', false)
            ->assertDontSee(self::KEY);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://map-service.test/api/v1/embed-links'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY)
            && $request['mall_ref'] === 'demo-mall'
            && $request['to'] === ['unit:G-101']
            && $request['step_free'] === true
            && $request['lang'] === 'en'
            && ! isset($request['from']));
    }

    public function test_the_map_speaks_bangla_when_the_visitor_does(): void
    {
        $this->fakeEmbed();

        $this->withSession(['locale' => 'bn'])->get(route('maps.show', ['publicMall' => 'demo-mall']))
            ->assertOk()
            ->assertSee('ম্যাপ');

        Http::assertSent(fn (Request $request): bool => $request['lang'] === 'bn');
    }

    public function test_a_badly_written_place_is_dropped_with_a_notice(): void
    {
        $this->fakeEmbed();

        $this->get(route('maps.show', ['publicMall' => 'demo-mall', 'to' => 'G-101', 'from' => "unit:\x01"]))
            ->assertOk()
            ->assertSee('That place is not on the map. Choose it from the list.')
            ->assertSee(e(self::EMBED), false);

        Http::assertSent(fn (Request $request): bool => ! isset($request['to']) && ! isset($request['from']));
    }

    public function test_an_unknown_destination_falls_back_to_the_plain_map(): void
    {
        Http::fake(['map-service.test/api/v1/embed-links' => Http::sequence()
            ->push(['message' => 'unit:Z-9 is not on this map.', 'reason' => 'unknown_place'], 422)
            ->push(['data' => ['url' => self::EMBED, 'expires_at' => 'x', 'map_id' => 'map-1', 'mall_ref' => 'demo-mall']], 201)]);

        $this->get(route('maps.show', ['publicMall' => 'demo-mall', 'to' => 'unit:Z-9']))
            ->assertOk()
            ->assertSee('That place is not on the map. Choose it from the list.')
            ->assertSee(e(self::EMBED), false);
    }

    public function test_a_mall_without_a_map_or_a_service_that_is_down_shows_a_notice(): void
    {
        Http::fake(['map-service.test/*' => Http::sequence()
            ->push(['message' => 'There is no published map for this mall.', 'reason' => 'no_map'], 404)
            ->push(['message' => 'The API key is unknown, revoked or expired.'], 401)]);

        $this->get(route('maps.show', ['publicMall' => 'demo-mall']))->assertNotFound()->assertSee('This mall has no indoor map yet.')->assertDontSee('<iframe', false);
        $this->get(route('maps.show', ['publicMall' => 'demo-mall']))->assertStatus(503)->assertSee('The map is not available right now.');
    }

    public function test_an_answer_without_a_link_is_treated_as_unavailable(): void
    {
        Http::fake(['map-service.test/api/v1/embed-links' => Http::response(['data' => []], 201)]);

        $this->get(route('maps.show', ['publicMall' => 'demo-mall']))->assertStatus(503)->assertDontSee('<iframe', false);
    }

    public function test_a_qr_code_opens_the_map_where_it_hangs(): void
    {
        Http::fake([
            'map-service.test/api/v1/checkpoints/k3j9x2m1q8wa' => Http::response(['data' => ['checkpoint_code' => 'G-C01', 'label' => 'Main entrance', 'mall_ref' => 'demo-mall', 'start' => 'checkpoint:k3j9x2m1q8wa']]),
            'map-service.test/api/v1/embed-links' => Http::response(['data' => ['url' => self::EMBED, 'expires_at' => 'x', 'map_id' => 'map-1', 'mall_ref' => 'demo-mall']], 201),
        ]);

        $this->get(route('maps.qr', ['publicMall' => 'demo-mall', 'slug' => 'k3j9x2m1q8wa']))
            ->assertOk()
            ->assertSee('You are at: Main entrance');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://map-service.test/api/v1/embed-links' && $request['from'] === 'checkpoint:k3j9x2m1q8wa');
    }

    public function test_a_qr_code_of_another_mall_or_an_unknown_one_is_not_found(): void
    {
        Http::fake(['map-service.test/api/v1/checkpoints/*' => Http::sequence()
            ->push(['data' => ['checkpoint_code' => 'G-C01', 'mall_ref' => 'other-mall', 'start' => 'checkpoint:k3j9x2m1q8wa']])
            ->push(['message' => 'This QR code is not known.', 'reason' => 'unknown'], 404)]);

        $this->get(route('maps.qr', ['publicMall' => 'demo-mall', 'slug' => 'k3j9x2m1q8wa']))->assertNotFound();
        $this->get(route('maps.qr', ['publicMall' => 'demo-mall', 'slug' => 'nosuchcode12']))->assertNotFound();
        $this->get('/malls/demo-mall/map/qr/BAD-SLUG')->assertNotFound();
    }

    public function test_a_retired_qr_code_still_opens_the_map_without_a_start(): void
    {
        Http::fake([
            'map-service.test/api/v1/checkpoints/*' => Http::response(['message' => 'This QR code is no longer in use.', 'reason' => 'retired'], 410),
            'map-service.test/api/v1/embed-links' => Http::response(['data' => ['url' => self::EMBED, 'expires_at' => 'x', 'map_id' => 'map-1', 'mall_ref' => 'demo-mall']], 201),
        ]);

        $this->get(route('maps.qr', ['publicMall' => 'demo-mall', 'slug' => 'k3j9x2m1q8wa']))
            ->assertOk()
            ->assertSee('This QR code is no longer in use.');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/embed-links') && ! isset($request['from']));
    }

    public function test_an_inactive_mall_has_no_map_page(): void
    {
        $this->mall->update(['status' => 'archived']);
        Http::fake();

        $this->get('/malls/demo-mall/map')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_the_mall_navigation_and_shop_page_link_to_the_map(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Cafe Brew']);
        ShopUnit::factory()->create(['shop_id' => $shop->getKey(), 'unit_code' => 'G-101']);

        $this->get(route('shops.show', ['publicMall' => 'demo-mall', 'publicShop' => $shop->slug]))
            ->assertOk()
            ->assertSee(route('maps.show', ['publicMall' => 'demo-mall']), false)
            ->assertSee(e(route('maps.show', ['publicMall' => 'demo-mall', 'to' => 'unit:G-101'])), false)
            ->assertSee('Show on map');
    }
}
