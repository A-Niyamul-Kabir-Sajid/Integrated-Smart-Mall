<?php

namespace Tests\Feature\Modules\AnalyticsReports;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Models\SearchEvent;
use App\Modules\AnalyticsReports\Models\ViewEvent;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\SharedPlatform\Events\VisitorActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ActivityCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

    private Mall $mall;

    private Shop $shop;

    private ShopProduct $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);
        $this->shop = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Cafe Brew']);
        $this->listing = ShopProduct::factory()->create(['shop_id' => $this->shop->getKey()]);
    }

    /**
     * @return $this
     */
    private function visitor(string $ip = '10.0.0.1', string $agent = self::BROWSER): static
    {
        return $this->withHeader('User-Agent', $agent)->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function shopSearchUrl(array $query = []): string
    {
        return route('shops.index', ['publicMall' => $this->mall->slug, ...$query]);
    }

    private function shopUrl(): string
    {
        return route('shops.show', ['publicMall' => $this->mall->slug, 'publicShop' => $this->shop->slug]);
    }

    private function productUrl(): string
    {
        return route('products.show', ['publicMall' => $this->mall->slug, 'publicProduct' => $this->listing->variant->product_id]);
    }

    public function test_a_shop_search_is_recorded_with_its_normalised_text_filters_and_result_count(): void
    {
        $this->visitor()->get($this->shopSearchUrl(['q' => '  Cafe  ']))->assertOk();
        $this->visitor('10.0.0.2')->get($this->shopSearchUrl(['q' => 'zzz nothing', 'category' => 'food', 'floor' => 'G']))->assertOk();

        $found = SearchEvent::query()->where('term', 'cafe')->sole();
        $this->assertSame('shop', $found->kind);
        $this->assertSame(1, $found->result_count);
        $this->assertFalse($found->signed_in);
        $this->assertSame(DatasetLabel::Real, $found->dataset_label);
        $this->assertSame($this->mall->getKey(), $found->mall_id);

        $missing = SearchEvent::query()->where('term', 'zzz nothing')->sole();
        $this->assertSame(0, $missing->result_count);
        $this->assertSame('food', $missing->category);
        $this->assertSame('G', $missing->floor);
    }

    public function test_repeating_a_search_within_thirty_minutes_is_one_search_but_other_visitors_and_terms_count(): void
    {
        $this->visitor()->get($this->shopSearchUrl(['q' => 'Cafe']))->assertOk();
        $this->visitor()->get($this->shopSearchUrl(['q' => 'cafe ']))->assertOk();
        $this->visitor()->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk();
        $this->assertSame(1, SearchEvent::query()->count(), 'Reloads and spelling variants are one search.');

        $this->visitor()->get($this->shopSearchUrl(['q' => 'tea']))->assertOk();
        $this->visitor()->get($this->shopSearchUrl(['q' => 'cafe', 'floor' => 'G']))->assertOk();
        $this->visitor('10.0.0.8')->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk();
        $this->assertSame(4, SearchEvent::query()->count(), 'A new term, a new filter or another visitor is a new search.');

        $this->travel(31)->minutes();
        $this->visitor()->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk();
        $this->assertSame(5, SearchEvent::query()->count());
    }

    public function test_a_product_search_is_recorded_as_a_product_search(): void
    {
        $this->visitor()->get(route('products.index', ['publicMall' => $this->mall->slug, 'q' => 'Unobtainium']))->assertOk();

        $event = SearchEvent::query()->sole();
        $this->assertSame('product', $event->kind);
        $this->assertSame('unobtainium', $event->term);
        $this->assertSame(0, $event->result_count);
    }

    public function test_empty_searches_later_pages_and_plain_browsing_are_not_recorded(): void
    {
        $this->visitor()->get($this->shopSearchUrl())->assertOk();
        $this->visitor()->get($this->shopSearchUrl(['q' => '   ']))->assertOk();
        $this->visitor()->get($this->shopSearchUrl(['category' => 'food']))->assertOk();
        $this->visitor()->get($this->shopSearchUrl(['q' => 'cafe', 'page' => 2]))->assertOk();

        $this->assertSame(0, SearchEvent::query()->count());
    }

    public function test_text_that_looks_personal_is_counted_but_never_stored(): void
    {
        $this->visitor()->get($this->shopSearchUrl(['q' => 'rina@example.test']))->assertOk();
        $this->visitor()->get($this->shopSearchUrl(['q' => '+880 1712-345678']))->assertOk();

        $this->assertSame(2, SearchEvent::query()->count());
        $this->assertSame(2, SearchEvent::query()->where('term_masked', true)->whereNull('term')->count());
        $this->assertStringNotContainsString('rina', json_encode(SearchEvent::query()->get()->toArray()));
        $this->assertStringNotContainsString('1712', json_encode(SearchEvent::query()->get()->toArray()));
    }

    public function test_events_hold_no_user_id_name_email_or_phone(): void
    {
        $forbidden = ['user_id', 'customer_user_id', 'email', 'phone', 'name', 'display_name', 'ip', 'ip_address', 'user_agent'];

        foreach (['activity_search_events', 'activity_view_events', 'activity_daily_summaries'] as $table) {
            $this->assertSame([], array_values(array_intersect($forbidden, Schema::getColumnListing($table))), "{$table} must not hold personal columns.");
        }
    }

    public function test_a_signed_in_visitor_is_only_flagged_as_signed_in(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->withHeader('User-Agent', self::BROWSER)->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk();

        $this->assertTrue(SearchEvent::query()->sole()->signed_in);
    }

    public function test_a_page_view_is_recorded_once_per_visitor_in_thirty_minutes(): void
    {
        $this->visitor()->get($this->shopUrl())->assertOk();
        $this->visitor()->get($this->shopUrl())->assertOk();
        $this->visitor()->get($this->shopUrl())->assertOk();

        $this->assertSame(1, ViewEvent::query()->where('kind', 'shop')->count());

        $this->visitor('10.0.0.9')->get($this->shopUrl())->assertOk();
        $this->assertSame(2, ViewEvent::query()->where('kind', 'shop')->count());

        $this->travel(31)->minutes();
        $this->visitor()->get($this->shopUrl())->assertOk();
        $this->assertSame(3, ViewEvent::query()->where('kind', 'shop')->count());
    }

    public function test_shop_and_product_views_carry_their_subject_and_an_anonymous_key(): void
    {
        $this->visitor()->get($this->shopUrl())->assertOk();
        $this->visitor()->get($this->productUrl())->assertOk();

        $shopView = ViewEvent::query()->where('kind', 'shop')->sole();
        $productView = ViewEvent::query()->where('kind', 'product')->sole();

        $this->assertSame($this->shop->getKey(), $shopView->shop_id);
        $this->assertNull($shopView->product_id);
        $this->assertSame($this->listing->variant->product_id, $productView->product_id);
        $this->assertNull($productView->shop_id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $shopView->visitor_key);
        $this->assertStringNotContainsString('10.0.0.1', json_encode(ViewEvent::query()->get()->toArray()));
    }

    public function test_the_visitor_key_changes_every_day_so_a_person_cannot_be_followed(): void
    {
        $this->visitor()->get($this->shopUrl())->assertOk();
        $today = ViewEvent::query()->sole()->visitor_key;

        $this->travel(1)->day();
        $this->visitor()->get($this->shopUrl())->assertOk();

        $keys = ViewEvent::query()->orderBy('activity_view_event_id')->pluck('visitor_key')->all();
        $this->assertCount(2, $keys);
        $this->assertNotSame($today, $keys[1]);
    }

    public function test_crawlers_and_requests_without_a_browser_are_not_counted(): void
    {
        foreach (['Googlebot/2.1 (+http://www.google.com/bot.html)', 'curl/8.4.0', 'Mozilla/5.0 (compatible; bingbot/2.0)', 'HeadlessChrome/120'] as $agent) {
            $this->visitor('10.1.1.1', $agent)->get($this->shopUrl())->assertOk();
            $this->visitor('10.1.1.1', $agent)->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.2'])->get($this->shopUrl())->assertOk();

        $this->assertSame(0, ViewEvent::query()->count());
        $this->assertSame(0, SearchEvent::query()->count());
    }

    public function test_the_shops_own_staff_and_the_malls_administrators_are_not_counted_but_customers_are(): void
    {
        $owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($owner, $this->shop, RoleCode::ShopOwner);
        $admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($admin, $this->mall, RoleCode::MallAdmin);
        $customer = User::factory()->create();

        foreach ([$owner, $admin] as $operator) {
            $this->actingAs($operator)->withHeader('User-Agent', self::BROWSER)->get($this->shopUrl())->assertOk();
            $this->actingAs($operator)->withHeader('User-Agent', self::BROWSER)->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk();
        }

        $this->assertSame(0, ViewEvent::query()->count());
        $this->assertSame(0, SearchEvent::query()->count());

        $this->actingAs($customer)->withHeader('User-Agent', self::BROWSER)->get($this->shopUrl())->assertOk();
        $this->assertSame(1, ViewEvent::query()->count());
    }

    public function test_a_recording_failure_never_changes_the_page(): void
    {
        Log::spy();
        Event::listen(VisitorActivity::class, function (): never {
            throw new RuntimeException('database is down');
        });

        $this->visitor()->get($this->shopUrl())->assertOk()->assertSee('Cafe Brew');
        $this->visitor()->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk()->assertSee('Cafe Brew');

        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_recorded_events_cannot_be_edited(): void
    {
        $this->visitor()->get($this->shopSearchUrl(['q' => 'cafe']))->assertOk();

        $this->expectException(LogicException::class);
        SearchEvent::query()->sole()->update(['term' => 'tampered']);
    }
}
