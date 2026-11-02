<?php

namespace Tests\Feature\Modules\MallShopDirectory\Http;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_opens_the_only_active_mall_directly(): void
    {
        $mall = Mall::factory()->create(['slug' => 'demo-city-mall']);
        Mall::factory()->draft()->create();

        $this->get(route('home'))->assertRedirect(route('malls.show', ['publicMall' => $mall->slug]));
    }

    public function test_home_lists_malls_when_there_are_several(): void
    {
        Mall::factory()->create(['name' => 'North Mall']);
        Mall::factory()->create(['name' => 'South Mall']);

        $this->get(route('home'))->assertOk()->assertSeeInOrder(['North Mall', 'South Mall']);
    }

    public function test_draft_and_unknown_malls_are_not_found(): void
    {
        $draft = Mall::factory()->draft()->create();

        $this->get('/malls/'.$draft->slug)->assertNotFound();
        $this->get('/malls/no-such-mall/shops')->assertNotFound();
    }

    public function test_mall_home_shows_categories_with_listed_shop_counts_and_floors(): void
    {
        $mall = Mall::factory()->create();
        $food = ShopCategory::factory()->create(['name' => 'Food & Drinks']);
        Shop::factory()->count(2)->for($mall)->create(['shop_category_id' => $food->getKey()]);
        Shop::factory()->for($mall)->draft()->create(['shop_category_id' => $food->getKey()]);
        Floor::factory()->for($mall)->create(['name' => 'Ground Floor']);

        $this->get(route('malls.show', ['publicMall' => $mall->slug]))
            ->assertOk()
            ->assertSee('Food &amp; Drinks', false)
            ->assertViewHas('categories', fn ($categories) => $categories->sole()->shops_count === 2)
            ->assertSee('Ground Floor');
    }

    public function test_directory_lists_shop_cards_with_location_and_paginates(): void
    {
        $mall = Mall::factory()->create();
        $floor = Floor::factory()->for($mall)->create(['code' => 'G', 'name' => 'Ground Floor']);
        foreach (range(1, 13) as $number) {
            ShopUnit::factory()
                ->for(Shop::factory()->for($mall)->create(['name' => sprintf('Shop %02d', $number)]))
                ->for($floor)
                ->create(['unit_code' => sprintf('G-%02d', $number)]);
        }

        $this->get(route('shops.index', ['publicMall' => $mall->slug]))
            ->assertOk()
            ->assertSee('13 shops found')
            ->assertSee('Shop 01')
            ->assertSee('Ground Floor · G-01')
            ->assertDontSee('Shop 13')
            ->assertSee('page=2', false);

        $this->get(route('shops.index', ['publicMall' => $mall->slug, 'page' => 2]))
            ->assertSee('Shop 13')
            ->assertDontSee('Shop 01');
    }

    public function test_filters_keep_their_values_and_show_an_empty_state(): void
    {
        $mall = Mall::factory()->create();
        Floor::factory()->for($mall)->create(['code' => 'F1']);

        $this->get(route('shops.index', ['publicMall' => $mall->slug, 'floor' => 'F1', 'open_now' => 1]))
            ->assertOk()
            ->assertSee('value="F1" selected', false)
            ->assertSee('No shops match these filters.');
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $mall = Mall::factory()->create();

        $this->get(route('shops.index', ['publicMall' => $mall->slug, 'open_now' => 'maybe']))->assertSessionHasErrors('open_now');
    }
}
