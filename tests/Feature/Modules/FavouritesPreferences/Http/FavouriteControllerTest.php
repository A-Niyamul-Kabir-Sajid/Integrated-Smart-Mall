<?php

namespace Tests\Feature\Modules\FavouritesPreferences\Http;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Services\FavouriteService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavouriteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function shopUrl(Shop $shop): string
    {
        return route('shops.show', ['publicMall' => $shop->mall->slug, 'publicShop' => $shop->slug]);
    }

    private function productUrl(ShopProduct $listing): string
    {
        return route('products.show', ['publicMall' => $listing->shop->mall->slug, 'publicProduct' => $listing->variant->product_id]);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->create();

        $this->get(route('account.saved'))->assertRedirect(route('login'));
        $this->post(route('favourites.shops.store', $shop->getKey()))->assertRedirect(route('login'));
        $this->post(route('favourites.listings.store', $listing->getKey()))->assertRedirect(route('login'));
        $this->get($this->shopUrl($shop))->assertOk()->assertSee('Sign in to save');
        $this->get($this->productUrl($listing))->assertOk()->assertSee('Sign in to save offers');
    }

    public function test_a_customer_saves_and_removes_a_shop_from_its_page(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['name' => 'Cafe Brew']);

        $this->actingAs($user)->get($this->shopUrl($shop))->assertSee('Save shop')->assertSee('aria-pressed="false"', false);

        $this->from($this->shopUrl($shop))->post(route('favourites.shops.store', $shop->getKey()))
            ->assertRedirect($this->shopUrl($shop))
            ->assertSessionHas('status', 'Cafe Brew was saved.');

        $this->get($this->shopUrl($shop))->assertSee('Saved ✓')->assertSee('aria-pressed="true"', false);
        $this->get(route('account.saved'))->assertOk()->assertSee('Cafe Brew');

        $this->delete(route('favourites.shops.destroy', $shop->getKey()))->assertSessionHas('status');
        $this->get(route('account.saved'))->assertDontSee('Cafe Brew');
    }

    public function test_a_customer_saves_an_offer_from_the_product_page(): void
    {
        $user = User::factory()->create();
        $listing = ShopProduct::factory()->create();

        $this->actingAs($user)->post(route('favourites.listings.store', $listing->getKey()))->assertSessionHas('status');

        $this->get($this->productUrl($listing))
            ->assertSee('id="favourite-'.$listing->getKey().'"', false)
            ->assertSee('Saved ✓');
        $this->get(route('account.saved'))->assertSee($listing->variant->product->name)->assertSee($listing->shop->name);
    }

    public function test_hidden_or_unknown_targets_cannot_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('favourites.shops.store', Shop::factory()->draft()->create()->getKey()))->assertNotFound();
        $this->post(route('favourites.listings.store', ShopProduct::factory()->archived()->create()->getKey()))->assertNotFound();
        $this->post('/favourites/shops/not-a-uuid')->assertNotFound();
        $this->assertSame([], app(FavouriteService::class)->savedShops($user->getKey())->all());
    }

    public function test_each_account_sees_only_its_own_saved_items(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        app(FavouriteService::class)->saveShop($other->getKey(), Shop::factory()->create(['name' => 'Someone Elses Shop']));

        $this->actingAs($me)->get(route('account.saved'))->assertOk()->assertDontSee('Someone Elses Shop')->assertSee('No saved shops yet');
    }

    public function test_removing_another_accounts_favourite_does_nothing(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        $shop = Shop::factory()->create();
        app(FavouriteService::class)->saveShop($other->getKey(), $shop);

        $this->actingAs($me)->delete(route('favourites.shops.destroy', $shop->getKey()));

        $this->assertTrue(app(FavouriteService::class)->hasSavedShop($other->getKey(), $shop->getKey()));
    }

    public function test_an_archived_saved_offer_is_marked_and_still_removable(): void
    {
        $user = User::factory()->create();
        $listing = ShopProduct::factory()->create();
        app(FavouriteService::class)->saveListing($user->getKey(), $listing);
        $listing->update(['status' => 'archived']);

        $this->actingAs($user)->get(route('account.saved'))
            ->assertSee('No longer available')
            ->assertDontSee('href="'.$this->productUrl($listing).'"', false);

        $this->delete(route('favourites.listings.destroy', $listing->getKey()));
        $this->get(route('account.saved'))->assertSee('No saved offers yet');
    }
}
