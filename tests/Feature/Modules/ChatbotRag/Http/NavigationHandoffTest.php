<?php

namespace Tests\Feature\Modules\ChatbotRag\Http;

use App\Modules\ChatbotRag\Services\Assistant\NavigationHandoff;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\ChatbotRag\BuildsAssistantMall;
use Tests\TestCase;

class NavigationHandoffTest extends TestCase
{
    use BuildsAssistantMall, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildMall();
    }

    private function go(string $type, string $id, string $mall = 'harbour-mall')
    {
        return $this->get(route('assistant.go', ['publicMall' => $mall, 'type' => $type, 'id' => $id]));
    }

    public function test_a_shop_destination_opens_the_shops_page_with_its_location(): void
    {
        $this->go('shop', $this->cafe->getKey())->assertRedirect(route('shops.show', ['publicMall' => 'harbour-mall', 'publicShop' => 'cafe-brew']));
        $this->followingRedirects()->go('shop', $this->cafe->getKey())->assertOk()->assertSee('Cafe Brew')->assertSee('Ground Floor')->assertSee('Unit G-01');
    }

    public function test_a_recommended_offer_leads_to_the_shop_that_sells_it(): void
    {
        $this->go('listing', $this->notebook->getKey())->assertRedirect(route('shops.show', ['publicMall' => 'harbour-mall', 'publicShop' => 'book-nook']));
        $this->go('listing', $this->colaAtCafe->getKey())->assertRedirect(route('shops.show', ['publicMall' => 'harbour-mall', 'publicShop' => 'cafe-brew']));
    }

    public function test_a_facility_destination_opens_the_facility_finder_on_its_floor(): void
    {
        $this->go('facility', $this->desk->getKey())->assertRedirect(route('facilities.index', ['publicMall' => 'harbour-mall', 'type' => 'information', 'floor' => 'G']));
        $this->followingRedirects()->go('facility', $this->desk->getKey())->assertOk()->assertSee('Information Desk');
    }

    public function test_a_suspended_shop_is_still_a_destination_because_customers_can_see_it(): void
    {
        $this->cafe->update(['status' => 'closed']);

        $this->go('shop', $this->cafe->getKey())->assertRedirect(route('shops.show', ['publicMall' => 'harbour-mall', 'publicShop' => 'cafe-brew']));
    }

    public function test_destinations_that_are_not_public_are_not_found(): void
    {
        $draft = $this->shopIn($this->mall, 'Tech Galaxy', 'electronics', 'Electronics', 'Awaiting approval.', $this->ground, 'G-07', ['10:00', '21:00'], 'draft');
        $archivedFacility = Facility::factory()->for($this->ground)->archived()->create(['name' => 'Old Kiosk']);
        $this->water->update(['status' => 'archived']);
        $this->books->update(['status' => 'archived']);

        $this->go('shop', $draft->getKey())->assertNotFound();
        $this->go('shop', $this->books->getKey())->assertNotFound();
        $this->go('listing', $this->water->getKey())->assertNotFound();
        $this->go('listing', $this->notebook->getKey())->assertNotFound();
        $this->go('facility', $archivedFacility->getKey())->assertNotFound();
    }

    public function test_destinations_of_another_mall_are_not_found_through_this_mall(): void
    {
        $other = Mall::factory()->create(['slug' => 'other-mall']);
        $theirShop = $this->shopIn($other, 'Far Bazaar', 'groceries', 'Groceries', 'Elsewhere.', null, null, ['09:00', '22:00']);
        $theirOffer = $this->offer($theirShop, 'Far Tea', 'Drinks', 'Tea.', '15.00');

        $this->go('shop', $theirShop->getKey())->assertNotFound();
        $this->go('listing', $theirOffer->getKey())->assertNotFound();
        $this->go('facility', $this->desk->getKey(), 'other-mall')->assertNotFound();
        $this->go('shop', $theirShop->getKey(), 'other-mall')->assertRedirect(route('shops.show', ['publicMall' => 'other-mall', 'publicShop' => 'far-bazaar']));
    }

    public function test_unknown_ids_malformed_ids_and_unknown_kinds_are_not_found(): void
    {
        $this->go('shop', (string) Str::uuid())->assertNotFound();
        $this->get('/malls/harbour-mall/assistant/go/shop/not-a-uuid')->assertNotFound();
        $this->get('/malls/harbour-mall/assistant/go/user/'.Str::uuid())->assertNotFound();
        $this->get('/malls/harbour-mall/assistant/go/shop/'.$this->cafe->getKey().'/../../../admin')->assertNotFound();
    }

    public function test_shops_are_linked_only_when_the_text_names_them_as_whole_words(): void
    {
        $handoff = app(NavigationHandoff::class);

        $found = $handoff->shopsMentionedIn($this->mall, 'You can get coffee at CAFE BREW; the bookshop is called Book Nook.');
        $none = $handoff->shopsMentionedIn($this->mall, 'The cafe brews coffee; Fresh Martian snacks are not sold. Book Nooks are cosy.');
        $skipped = $handoff->shopsMentionedIn($this->mall, 'Cafe Brew and Fresh Mart', [$this->cafe->getKey()]);

        $this->assertSame(['Book Nook', 'Cafe Brew'], array_column($found, 'label'));
        $this->assertSame([], $none);
        $this->assertSame(['Fresh Mart'], array_column($skipped, 'label'));
        $this->assertSame(['type' => 'shop', 'id' => $this->books->getKey(), 'label' => 'Book Nook', 'detail' => 'First Floor, unit F1-01'], $found[0]);
    }
}
