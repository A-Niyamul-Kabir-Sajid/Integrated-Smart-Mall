<?php

namespace Tests\Feature\Modules\ProductsCatalogue\Http;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProductPagesTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Product $water;

    private ShopProduct $cheap;

    private ShopProduct $dear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['slug' => 'demo-city-mall']);
        $floor = Floor::factory()->for($this->mall)->create(['name' => 'Ground Floor']);
        $fresh = Shop::factory()->for($this->mall)->create(['name' => 'Fresh Mart', 'slug' => 'fresh-mart']);
        $corner = Shop::factory()->for($this->mall)->create(['name' => 'Juice Corner', 'slug' => 'juice-corner']);
        ShopUnit::factory()->for($fresh)->for($floor)->create(['unit_code' => 'G-02']);

        $this->water = Product::factory()->create(['name' => 'Mineral Water', 'brand' => 'AquaPure']);
        $bottle = ProductVariant::factory()->for($this->water)->create(['label' => '500 ml bottle', 'attributes_json' => ['volume' => '500 ml']]);
        $this->cheap = ShopProduct::factory()->for($fresh)->for($bottle, 'variant')->create(['price_amount' => 20]);
        $this->dear = ShopProduct::factory()->for($corner)->for($bottle, 'variant')->create(['price_amount' => 25, 'availability_status' => 'unavailable']);
    }

    private function productUrl(string $id): string
    {
        return route('products.show', ['publicMall' => $this->mall->slug, 'publicProduct' => $id]);
    }

    public function test_product_search_lists_the_cheapest_offer_and_keeps_filters(): void
    {
        $this->get(route('products.index', ['publicMall' => $this->mall->slug, 'q' => 'water', 'sort' => 'price_asc', 'max_price' => 100]))
            ->assertOk()
            ->assertSee('1 product found')
            ->assertSee('from ৳20')
            ->assertSee('2 offers')
            ->assertSee('value="water"', false)
            ->assertSee('value="price_asc" selected', false)
            ->assertSee('href="'.$this->productUrl($this->water->getKey()).'"', false);
    }

    public function test_invalid_price_ranges_and_sorts_are_rejected(): void
    {
        $this->get(route('products.index', ['publicMall' => $this->mall->slug, 'min_price' => 50, 'max_price' => 10, 'sort' => 'random']))
            ->assertSessionHasErrors(['max_price', 'sort']);
    }

    public function test_product_page_lists_offers_cheapest_first_with_location_and_availability(): void
    {
        $this->get($this->productUrl($this->water->getKey()))
            ->assertOk()
            ->assertSee('Sold by 2 shops')
            ->assertSeeInOrder(['Fresh Mart', '500 ml bottle · Ground Floor · G-02', '৳20.00', 'Juice Corner', 'Out of stock', '৳25.00'])
            ->assertSee('name="listings[]"', false);
    }

    public function test_products_not_buyable_in_this_mall_are_not_found(): void
    {
        $archived = Product::factory()->create(['status' => 'archived']);
        $elsewhere = ShopProduct::factory()->create()->variant->product;

        $this->get($this->productUrl($archived->getKey()))->assertNotFound();
        $this->get($this->productUrl($elsewhere->getKey()))->assertNotFound();
        $this->get($this->productUrl('not-a-uuid'))->assertNotFound();
    }

    public function test_comparison_shows_selected_visible_offers_side_by_side(): void
    {
        $foreign = ShopProduct::factory()->create();

        $this->get(route('products.compare', ['publicMall' => $this->mall->slug, 'listings' => [$this->dear->getKey(), $this->cheap->getKey(), $foreign->getKey()]]))
            ->assertOk()
            ->assertSeeInOrder(['Juice Corner', 'Fresh Mart'])
            ->assertSee('Volume: 500 ml')
            ->assertDontSee($foreign->shop->name);
    }

    public function test_comparison_accepts_at_most_four_valid_ids(): void
    {
        $url = route('products.compare', ['publicMall' => $this->mall->slug]);

        $this->get($url.'?'.http_build_query(['listings' => array_fill(0, 5, $this->cheap->getKey())]))->assertSessionHasErrors('listings');
        $this->get($url.'?'.http_build_query(['listings' => ['nope']]))->assertSessionHasErrors('listings.0');
    }

    public function test_comparing_nothing_returns_to_the_product_page_with_a_message(): void
    {
        $this->from($this->productUrl($this->water->getKey()))
            ->followingRedirects()
            ->get(route('products.compare', ['publicMall' => $this->mall->slug]))
            ->assertOk()
            ->assertSee('Sold by 2 shops')
            ->assertSee('Tick at least one shop to compare.');
    }

    public function test_comparing_too_many_offers_explains_the_limit_and_keeps_the_ticks(): void
    {
        $url = route('products.compare', ['publicMall' => $this->mall->slug]).'?'.http_build_query(['listings' => array_fill(0, 5, $this->cheap->getKey())]);

        $this->from($this->productUrl($this->water->getKey()))
            ->followingRedirects()
            ->get($url)
            ->assertOk()
            ->assertSee('You can compare up to 4 offers at a time.')
            ->assertSee('value="'.$this->cheap->getKey().'" checked', false)
            ->assertDontSee('value="'.$this->dear->getKey().'" checked', false);
    }

    public function test_navigation_and_shop_pages_lead_to_products(): void
    {
        $this->get(route('shops.show', ['publicMall' => $this->mall->slug, 'publicShop' => 'fresh-mart']))
            ->assertSee('href="'.route('products.index', ['publicMall' => $this->mall->slug, 'shop' => 'fresh-mart']).'"', false)
            ->assertSee('href="'.route('products.index', ['publicMall' => $this->mall->slug]).'"', false);

        $this->get(route('products.index', ['publicMall' => $this->mall->slug, 'shop' => 'fresh-mart']))->assertSee('1 product found');
    }
}
