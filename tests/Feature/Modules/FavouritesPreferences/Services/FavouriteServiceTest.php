<?php

namespace Tests\Feature\Modules\FavouritesPreferences\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Models\FavouriteProduct;
use App\Modules\FavouritesPreferences\Models\FavouriteShop;
use App\Modules\FavouritesPreferences\Services\FavouriteService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavouriteServiceTest extends TestCase
{
    use RefreshDatabase;

    private FavouriteService $favourites;

    protected function setUp(): void
    {
        parent::setUp();

        $this->favourites = app(FavouriteService::class);
    }

    public function test_saving_a_shop_twice_keeps_one_row(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $this->favourites->saveShop($user->getKey(), $shop);
        $this->favourites->saveShop($user->getKey(), $shop);

        $this->assertSame(1, FavouriteShop::query()->count());
        $this->assertTrue($this->favourites->hasSavedShop($user->getKey(), $shop->getKey()));
    }

    public function test_the_database_rejects_a_duplicate_favourite(): void
    {
        $user = User::factory()->create();
        $listing = ShopProduct::factory()->create();
        $row = ['user_id' => $user->getKey(), 'shop_product_id' => $listing->getKey(), 'created_at' => now()];

        FavouriteProduct::query()->insert($row);

        $this->expectException(QueryException::class);
        FavouriteProduct::query()->insert($row);
    }

    public function test_removing_is_idempotent_and_only_touches_the_given_account(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        $shop = Shop::factory()->create();
        $this->favourites->saveShop($me->getKey(), $shop);
        $this->favourites->saveShop($other->getKey(), $shop);

        $this->favourites->removeShop($me->getKey(), $shop->getKey());
        $this->favourites->removeShop($me->getKey(), $shop->getKey());

        $this->assertFalse($this->favourites->hasSavedShop($me->getKey(), $shop->getKey()));
        $this->assertTrue($this->favourites->hasSavedShop($other->getKey(), $shop->getKey()));
    }

    public function test_only_shops_customers_can_see_may_be_saved(): void
    {
        $this->assertNotNull($this->favourites->saveableShop(Shop::factory()->create()->getKey()));
        $this->assertNotNull($this->favourites->saveableShop(Shop::factory()->closed()->create()->getKey()));
        $this->assertNull($this->favourites->saveableShop(Shop::factory()->draft()->create()->getKey()));
        $this->assertNull($this->favourites->saveableShop(Shop::factory()->archived()->create()->getKey()));
        $this->assertNull($this->favourites->saveableShop(Shop::factory()->for(Mall::factory()->draft())->create()->getKey()));
        $this->assertNull($this->favourites->saveableShop('01a1a301-0000-7000-8000-000000000000'));
    }

    public function test_only_visible_listings_may_be_saved(): void
    {
        $this->assertNotNull($this->favourites->saveableListing(ShopProduct::factory()->create()->getKey()));
        $this->assertNull($this->favourites->saveableListing(ShopProduct::factory()->archived()->create()->getKey()));
        $this->assertNull($this->favourites->saveableListing(ShopProduct::factory()->for(Shop::factory()->closed())->create()->getKey()));
        $this->assertNull($this->favourites->saveableListing('01a1a301-0000-7000-8000-000000000000'));
    }

    public function test_saved_items_that_disappear_stay_listed_as_unavailable(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->create();
        $this->favourites->saveShop($user->getKey(), $shop);
        $this->favourites->saveListing($user->getKey(), $listing);

        $shop->update(['status' => 'archived']);
        $listing->update(['status' => 'archived']);

        $this->assertFalse($this->favourites->savedShops($user->getKey())->sole()->is_available);
        $this->assertFalse($this->favourites->savedListings($user->getKey())->sole()->is_available);
    }

    public function test_saved_listing_ids_are_limited_to_the_account_and_the_asked_ids(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        [$first, $second, $third] = ShopProduct::factory()->count(3)->create();
        $this->favourites->saveListing($me->getKey(), $first);
        $this->favourites->saveListing($me->getKey(), $third);
        $this->favourites->saveListing($other->getKey(), $second);

        $this->assertSame([$first->getKey()], $this->favourites->savedListingIds($me->getKey(), [$first->getKey(), $second->getKey()]));
        $this->assertSame([], $this->favourites->savedListingIds($me->getKey(), []));
    }

    public function test_deleting_an_account_deletes_its_favourites(): void
    {
        $user = User::factory()->create();
        $this->favourites->saveShop($user->getKey(), Shop::factory()->create());

        $user->delete();

        $this->assertSame(0, FavouriteShop::query()->count());
    }
}
