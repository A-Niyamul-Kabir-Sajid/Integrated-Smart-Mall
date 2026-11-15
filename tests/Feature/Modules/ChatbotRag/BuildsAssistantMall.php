<?php

namespace Tests\Feature\Modules\ChatbotRag;

use App\Modules\ChatbotRag\Contracts\AnswerGenerator;
use App\Modules\ChatbotRag\DTOs\AssistantReplyData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Services\Assistant\AssistantService;
use App\Modules\ChatbotRag\Services\Generation\FallbackAnswerGenerator;
use App\Modules\ChatbotRag\Services\Generation\GeneratedAnswerValidator;
use App\Modules\ChatbotRag\Services\Generation\TemplateAnswerGenerator;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeSyncService;
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
use Tests\Fixtures\Assistant\FakeAnswerGenerator;

/**
 * A small mall for the assistant tests: Harbour Mall in Dhaka with two floors, three shops, a few
 * offers, four facilities and a visitor FAQ. The clock stands at Monday 2026-11-16 06:00 UTC, which
 * is 12:00 in the mall. No model is configured, so answers come from the template driver unless a
 * test installs a fake model with useModel().
 */
trait BuildsAssistantMall
{
    use CreatesKnowledge;

    public const FAQ = "Q: What does parking cost?\nA: Parking costs BDT 30 for the first two hours and BDT 20 for every hour after that.\n\nQ: Is there free Wi-Fi?\nA: Yes. Connect to the network HM-Guest; no password is needed.\n\nQ: Where is the lost and found?\nA: Lost items are kept at the Information Desk on the Ground Floor for 30 days.";

    protected Mall $mall;

    protected Floor $ground;

    protected Floor $first;

    protected Shop $cafe;

    protected Shop $mart;

    protected Shop $books;

    protected ShopProduct $coldCoffee;

    protected ShopProduct $colaAtMart;

    protected ShopProduct $colaAtCafe;

    protected ShopProduct $water;

    protected ShopProduct $notebook;

    protected Facility $desk;

    protected function buildMall(): void
    {
        $this->travelTo('2026-11-16 06:00:00');

        $this->mall = Mall::factory()->create(['name' => 'Harbour Mall', 'slug' => 'harbour-mall', 'timezone' => 'Asia/Dhaka']);
        $this->ground = Floor::factory()->for($this->mall)->create(['code' => 'G', 'name' => 'Ground Floor', 'sort_order' => 0]);
        $this->first = Floor::factory()->for($this->mall)->create(['code' => 'F1', 'name' => 'First Floor', 'sort_order' => 1]);

        $this->cafe = $this->shopIn($this->mall, 'Cafe Brew', 'food-drinks', 'Food & Drinks', 'Coffee, tea and fresh pastries.', $this->ground, 'G-01', ['08:00', '23:00']);
        $this->mart = $this->shopIn($this->mall, 'Fresh Mart', 'groceries', 'Groceries', 'Everyday groceries and snacks.', $this->ground, 'G-02', ['09:00', '22:00']);
        $this->books = $this->shopIn($this->mall, 'Book Nook', 'books', 'Books', 'Books and notebooks.', $this->first, 'F1-01', ['15:00', '21:00']);

        $this->coldCoffee = $this->offer($this->cafe, 'Cold Coffee', 'Drinks', 'Iced coffee with milk.', '220.00');
        $this->colaAtMart = $this->offer($this->mart, 'Cola Soft Drink', 'Drinks', 'Chilled carbonated cola.', '40.00');
        $this->colaAtCafe = $this->offer($this->cafe, 'Cola Soft Drink', 'Drinks', 'Chilled carbonated cola.', '55.00');
        $this->water = $this->offer($this->mart, 'Mineral Water', 'Drinks', 'Still drinking water.', '20.00', ['availability_status' => 'unavailable']);
        $this->notebook = $this->offer($this->books, 'A5 Notebook', 'Stationery', 'Ruled notebook, 120 pages.', '90.00');

        $this->desk = Facility::factory()->for($this->ground)->create(['name' => 'Information Desk', 'facility_type' => 'information', 'location_note' => 'Main entrance']);
        Facility::factory()->for($this->ground)->create(['name' => 'Washroom', 'facility_type' => 'toilet', 'location_note' => 'Behind the escalator']);
        Facility::factory()->for($this->first)->create(['name' => 'Prayer Room', 'facility_type' => 'prayer_room', 'location_note' => 'Near Book Nook', 'is_accessible' => false]);
        Facility::factory()->for($this->first)->temporarilyClosed()->create(['name' => 'Washroom', 'facility_type' => 'toilet', 'location_note' => 'East corridor']);

        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);
    }

    /**
     * @param  array{0: string, 1: string}  $hours  Opening and closing time on every day
     */
    protected function shopIn(Mall $mall, string $name, string $categorySlug, string $categoryName, string $description, ?Floor $floor, ?string $unit, array $hours, string $status = 'active'): Shop
    {
        $shop = Shop::factory()->for($mall)->create([
            'name' => $name, 'slug' => str($name)->slug()->toString(), 'description' => $description, 'status' => $status,
            'shop_category_id' => ShopCategory::query()->firstOrCreate(['slug' => $categorySlug], ['name' => $categoryName])->getKey(),
        ]);

        if ($floor !== null && $unit !== null) {
            ShopUnit::factory()->for($shop)->create(['floor_id' => $floor->getKey(), 'unit_code' => $unit]);
        }

        ShopOpeningHour::query()->insert(array_map(fn (int $weekday): array => [
            'shop_id' => $shop->getKey(), 'weekday' => $weekday, 'interval_no' => 1, 'opens_at' => $hours[0], 'closes_at' => $hours[1], 'closes_next_day' => false,
        ], range(1, 7)));

        return $shop;
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    protected function offer(Shop $shop, string $product, string $category, string $description, string $price, array $listing = []): ShopProduct
    {
        $categoryModel = ProductCategory::query()->firstOrCreate(['slug' => str($category)->slug()->toString()], ['name' => $category]);
        $productModel = Product::query()->where('name', $product)->first()
            ?? Product::factory()->create(['name' => $product, 'brand' => null, 'description' => $description, 'product_category_id' => $categoryModel->getKey()]);
        $variant = $productModel->variants()->first() ?? ProductVariant::factory()->for($productModel)->create();

        return ShopProduct::factory()->for($shop)->for($variant, 'variant')->create(['price_amount' => $price, ...$listing]);
    }

    /**
     * Captures shops, offers and floors as knowledge documents, as the hourly sync does.
     */
    protected function captureMallRecords(?Mall $mall = null): void
    {
        app(KnowledgeSyncService::class)->syncMall($mall ?? $this->mall);
    }

    protected function ask(string $question, AccessScope $scope = AccessScope::Public): AssistantReplyData
    {
        return app(AssistantService::class)->respond($this->mall, $scope, null, $question);
    }

    /**
     * Installs a fake model as the assistant's model driver, with the real fallback and validator
     * around it.
     */
    protected function useModel(FakeAnswerGenerator $model): FakeAnswerGenerator
    {
        $this->app->bind(AnswerGenerator::class, fn ($app): AnswerGenerator => new FallbackAnswerGenerator(
            $model, $app->make(TemplateAnswerGenerator::class), $app->make(GeneratedAnswerValidator::class),
        ));

        return $model;
    }
}
