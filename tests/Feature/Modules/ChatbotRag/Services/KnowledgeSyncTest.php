<?php

namespace Tests\Feature\Modules\ChatbotRag\Services;

use App\Modules\ChatbotRag\DTOs\RetrievedChunkData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Models\DocumentRevision;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeSyncService;
use App\Modules\ChatbotRag\Services\Retrieval\KnowledgeRetriever;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Events\ShopStatusChanged;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ShopOwnerOperations\Models\ShopPromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeSyncTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Floor $ground;

    private Shop $cafe;

    private ShopProduct $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-15 06:00:00');

        $this->mall = Mall::factory()->create(['name' => 'Harbour Mall', 'timezone' => 'Asia/Dhaka']);
        $this->ground = Floor::factory()->for($this->mall)->create(['code' => 'G', 'name' => 'Ground Floor', 'sort_order' => 0]);
        $this->cafe = $this->shop('Cafe Brew', 'Coffee and fresh pastries.', 'G-01');
        ShopOpeningHour::query()->insert(['shop_id' => $this->cafe->getKey(), 'weekday' => 1, 'interval_no' => 1, 'opens_at' => '08:00', 'closes_at' => '23:00', 'closes_next_day' => false]);
        Facility::factory()->for($this->ground)->create(['name' => 'Washroom', 'facility_type' => 'toilet', 'location_note' => 'Behind the escalator', 'is_accessible' => true]);
        $this->coffee = $this->listing($this->cafe, 'Cold Coffee', 'Iced coffee with milk.', '220.00');
    }

    private function shop(string $name, string $description, ?string $unit, string $status = 'active', ?Mall $mall = null): Shop
    {
        $shop = Shop::factory()->for($mall ?? $this->mall)->create([
            'name' => $name, 'description' => $description, 'status' => $status,
            'shop_category_id' => ShopCategory::query()->firstOrCreate(['slug' => 'food-drinks'], ['name' => 'Food & Drinks'])->getKey(),
        ]);

        if ($unit !== null) {
            ShopUnit::factory()->for($shop)->create(['floor_id' => $this->ground->getKey(), 'unit_code' => $unit]);
        }

        return $shop;
    }

    private function listing(Shop $shop, string $product, string $description, string $price, array $listing = []): ShopProduct
    {
        $category = ProductCategory::query()->firstOrCreate(['slug' => 'drinks'], ['name' => 'Drinks']);
        $variant = ProductVariant::factory()->for(Product::factory()->create(['name' => $product, 'brand' => 'Brew Co', 'description' => $description, 'product_category_id' => $category->getKey()]))->create(['label' => 'Regular']);

        return ShopProduct::factory()->for($shop)->for($variant, 'variant')->create(['price_amount' => $price, ...$listing]);
    }

    private function sync(): array
    {
        return app(KnowledgeSyncService::class)->syncMall($this->mall);
    }

    private function text(string $locator): string
    {
        return KnowledgeDocument::query()->where('source_locator', $locator)->sole()->currentRevision->content_text;
    }

    public function test_the_first_sync_captures_the_floor_guide_each_listed_shop_and_each_visible_offer(): void
    {
        $result = $this->sync();

        $this->assertSame(['created' => 3, 'updated' => 0, 'unchanged' => 0, 'withdrawn' => 0], $result);
        $this->assertEqualsCanonicalizing(
            ['system:floors', 'system:shop:'.$this->cafe->getKey(), 'system:listing:'.$this->coffee->getKey()],
            KnowledgeDocument::query()->pluck('source_locator')->all(),
        );
        $this->assertSame(0, KnowledgeDocument::query()->where('access_scope', '!=', 'public')->count());
    }

    public function test_the_floor_guide_names_floors_shops_and_facilities(): void
    {
        $this->sync();

        $this->assertSame(
            "# Floors of Harbour Mall\nHarbour Mall has 1 floor: Ground Floor (G).\n\n# Ground Floor (G)\nShops on Ground Floor: Cafe Brew.\nWashroom (Toilet) is on Ground Floor: Behind the escalator. Step-free access.",
            $this->text('system:floors'),
        );
    }

    public function test_a_shop_profile_holds_location_opening_hours_and_public_offers_but_no_coupon_code(): void
    {
        ShopPromotion::factory()->for($this->cafe)->create(['title' => 'Winter treat', 'description' => 'On every hot drink.', 'discount_value' => '15.00', 'ends_at' => '2026-11-30 12:00:00']);
        ShopPromotion::factory()->for($this->cafe)->coupon('SECRET25')->create(['title' => 'Members only']);
        ShopPromotion::factory()->for($this->cafe)->ended()->create(['title' => 'Last month']);

        $this->sync();
        $profile = $this->text('system:shop:'.$this->cafe->getKey());

        $this->assertStringContainsString("Cafe Brew is a Food & Drinks shop at Harbour Mall.\nCoffee and fresh pastries.\nLocation of Cafe Brew: Ground Floor (G), unit G-01.", $profile);
        $this->assertStringContainsString("# Opening hours of Cafe Brew\nMonday: 08:00 – 23:00.\nTuesday: closed.", $profile);
        $this->assertStringContainsString("# Offers at Cafe Brew\nWinter treat: 15% off, until 30 November 2026. On every hot drink.", $profile);
        $this->assertStringNotContainsString('SECRET25', $profile);
        $this->assertStringNotContainsString('Members only', $profile);
        $this->assertStringNotContainsString('Last month', $profile);
    }

    public function test_an_offer_document_describes_the_product_and_its_shop_without_price_or_stock(): void
    {
        $this->sync();
        $document = KnowledgeDocument::query()->where('source_locator', 'system:listing:'.$this->coffee->getKey())->sole();

        $this->assertSame('Cold Coffee (Regular) at Cafe Brew', $document->title);
        $this->assertSame($this->coffee->getKey(), $document->shop_product_id);
        $this->assertSame($this->cafe->getKey(), $document->shop_id);
        $this->assertSame(
            "Cold Coffee by Brew Co is a product in the category Drinks.\nIced coffee with milk.\nOption: Regular.\nSold by Cafe Brew on Ground Floor, unit G-01.",
            $document->currentRevision->content_text,
        );
        $this->assertStringNotContainsString('220', $document->currentRevision->content_text);
    }

    public function test_records_that_are_not_public_are_never_captured(): void
    {
        $draft = $this->shop('Tech Galaxy', 'Awaiting approval.', 'G-02', 'draft');
        $archived = $this->shop('Old Bazaar', 'Gone.', 'G-03', 'archived');
        $elsewhere = $this->shop('Far Away Cafe', 'Another mall.', null, 'active', Mall::factory()->create());
        $this->listing($draft, 'Prototype Phone', 'Not on sale.', '99.00');
        $this->listing($this->cafe, 'Retired Latte', 'No longer sold.', '50.00', ['status' => 'archived']);
        Facility::factory()->for($this->ground)->archived()->create(['name' => 'Old Kiosk']);

        $this->sync();
        $captured = implode("\n", DocumentRevision::query()->pluck('content_text')->all()).implode("\n", KnowledgeDocument::query()->pluck('title')->all());

        foreach (['Tech Galaxy', 'Old Bazaar', 'Far Away Cafe', 'Prototype Phone', 'Retired Latte', 'Old Kiosk'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $captured);
        }

        $this->assertSame(0, KnowledgeDocument::query()->whereIn('shop_id', [$draft->getKey(), $archived->getKey(), $elsewhere->getKey()])->count());
    }

    public function test_a_suspended_shop_keeps_a_profile_that_says_it_is_closed_and_loses_its_offers(): void
    {
        $this->cafe->update(['status' => 'closed']);

        $this->sync();

        $this->assertStringContainsString('Cafe Brew is closed at the moment and takes no orders.', $this->text('system:shop:'.$this->cafe->getKey()));
        $this->assertStringNotContainsString('Opening hours', $this->text('system:shop:'.$this->cafe->getKey()));
        $this->assertSame(0, KnowledgeDocument::query()->where('source_kind', 'product_listing')->count());
    }

    public function test_a_second_sync_without_changes_captures_nothing(): void
    {
        $this->sync();

        $result = $this->sync();

        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 3, 'withdrawn' => 0], $result);
        $this->assertSame(3, DocumentRevision::query()->count());
    }

    public function test_a_changed_record_gets_a_new_revision_and_the_others_are_left_alone(): void
    {
        $this->sync();
        $this->cafe->update(['description' => 'Coffee, tea and cakes.']);

        $result = $this->sync();

        $this->assertSame(['created' => 0, 'updated' => 1, 'unchanged' => 2, 'withdrawn' => 0], $result);
        $profile = KnowledgeDocument::query()->where('source_locator', 'system:shop:'.$this->cafe->getKey())->sole();
        $this->assertSame(2, $profile->currentRevision->revision_no);
        $this->assertStringContainsString('Coffee, tea and cakes.', $profile->currentRevision->content_text);
    }

    public function test_a_record_that_stops_being_public_is_withdrawn_and_comes_back_when_it_is_public_again(): void
    {
        $this->sync();
        $this->coffee->update(['status' => 'archived']);

        $this->assertSame(1, $this->sync()['withdrawn']);
        $document = KnowledgeDocument::query()->where('source_locator', 'system:listing:'.$this->coffee->getKey())->sole();
        $this->assertSame(DocumentStatus::Withdrawn, $document->status);
        $retrieved = app(KnowledgeRetriever::class)->retrieve($this->mall, AccessScope::Public, 'iced coffee with milk');
        $this->assertNotContains($this->coffee->getKey(), array_map(fn (RetrievedChunkData $chunk): ?string => $chunk->shopProductId, $retrieved));

        $this->coffee->update(['status' => 'active']);

        $this->assertSame(1, $this->sync()['updated']);
        $this->assertSame(DocumentStatus::Active, $document->refresh()->status);
        $this->assertSame(1, $document->revisions()->count());
    }

    public function test_hand_written_documents_are_not_touched_by_the_sync(): void
    {
        $written = KnowledgeDocument::factory()->for($this->mall)->create(['title' => 'Visitor FAQ']);

        $this->sync();

        $this->assertSame(DocumentStatus::Active, $written->refresh()->status);
        $this->assertSame('Visitor FAQ', $written->title);
    }

    public function test_a_shop_status_change_refreshes_the_malls_knowledge_at_once(): void
    {
        $this->sync();
        $this->cafe->update(['status' => 'archived']);

        ShopStatusChanged::dispatch($this->cafe->getKey(), $this->mall->getKey(), ShopStatus::Active, ShopStatus::Archived);

        $this->assertSame(DocumentStatus::Withdrawn, KnowledgeDocument::query()->where('source_locator', 'system:shop:'.$this->cafe->getKey())->sole()->status);
        $this->assertStringContainsString('No shop is listed on Ground Floor.', $this->text('system:floors'));
    }

    public function test_the_command_syncs_one_mall_by_slug_and_reports_the_counts(): void
    {
        $this->artisan('assistant:sync-knowledge', ['mall' => $this->mall->slug])
            ->expectsOutput($this->mall->slug.': 3 created, 0 updated, 0 unchanged, 0 withdrawn.')
            ->assertSuccessful();

        $this->artisan('assistant:sync-knowledge', ['mall' => 'no-such-mall'])->assertFailed();
    }
}
