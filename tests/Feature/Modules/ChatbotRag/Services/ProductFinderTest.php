<?php

namespace Tests\Feature\Modules\ChatbotRag\Services;

use App\Modules\ChatbotRag\DTOs\ProductQueryData;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Enums\AssistantTool;
use App\Modules\ChatbotRag\Services\Assistant\Tools\ProductQueryParser;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ReviewsModeration\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Modules\ChatbotRag\BuildsAssistantMall;
use Tests\TestCase;

class ProductFinderTest extends TestCase
{
    use BuildsAssistantMall, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildMall();
    }

    private function parse(string $request): ProductQueryData
    {
        return app(ProductQueryParser::class)->parse($request, $this->mall);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function requestsAndFilters(): array
    {
        return [
            'category from a general word' => ['Where can I get a cold drink?', ['keywords' => 'cold', 'categorySlug' => 'drinks']],
            'category from a synonym' => ['any beverages?', ['keywords' => '', 'categorySlug' => 'drinks']],
            'maximum price' => ['notebook under 100 taka', ['keywords' => 'notebook', 'maxPrice' => 100.0, 'minPrice' => null]],
            'minimum price' => ['coffee above BDT 150', ['keywords' => 'coffee', 'minPrice' => 150.0, 'maxPrice' => null]],
            'range in either order' => ['drinks between 300 and 50 tk', ['categorySlug' => 'drinks', 'minPrice' => 50.0, 'maxPrice' => 300.0]],
            'thousands' => ['earbuds up to 2.5k', ['keywords' => 'earbuds', 'maxPrice' => 2500.0]],
            'thousands separator' => ['earbuds below 2,900', ['keywords' => 'earbuds', 'maxPrice' => 2900.0]],
            'in stock' => ['mineral water in stock', ['keywords' => 'mineral water', 'availableOnly' => true]],
            'cheapest' => ['cheapest cola', ['keywords' => 'cola', 'sort' => 'price_asc']],
            'most expensive' => ['most expensive coffee', ['keywords' => 'coffee', 'sort' => 'price_desc']],
            'best rated' => ['top rated coffee', ['keywords' => 'coffee', 'sort' => 'rating']],
            'minimum rating' => ['coffee with 4 stars and above', ['keywords' => 'coffee', 'minRating' => 4.0]],
            'shop of the mall' => ['cola at Fresh Mart', ['keywords' => 'cola', 'shopSlug' => 'fresh-mart', 'shopName' => 'Fresh Mart']],
            'bangla price and product' => ['২০০ টাকার মধ্যে কফি', ['keywords' => 'coffee', 'maxPrice' => 200.0]],
            'bangla category' => ['ঠান্ডা পানীয় কোথায় পাব?', ['keywords' => 'cold', 'categorySlug' => 'drinks']],
            'nothing to look for' => ['how much is it?', ['keywords' => '', 'categorySlug' => null, 'shopSlug' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    #[DataProvider('requestsAndFilters')]
    public function test_a_request_in_plain_words_becomes_catalogue_filters(string $request, array $expected): void
    {
        $query = $this->parse($request);

        foreach ($expected as $field => $value) {
            $this->assertSame($value, $query->{$field}, "Field {$field} of \"{$request}\"");
        }
    }

    public function test_at_most_five_keywords_are_kept_and_operators_are_just_words(): void
    {
        $query = $this->parse("red' OR 1=1; green % blue _ yellow purple orange white");

        $this->assertSame('red green blue yellow purple', $query->keywords);
    }

    public function test_a_shop_of_another_mall_is_not_a_shop_filter(): void
    {
        $this->shopIn(Mall::factory()->create(), 'Far Bazaar', 'groceries', 'Groceries', 'Elsewhere.', null, null, ['09:00', '22:00']);

        $this->assertNull($this->parse('cola at Far Bazaar')->shopSlug);
    }

    public function test_the_cold_drink_question_returns_drinks_that_exist_with_their_live_price(): void
    {
        $reply = $this->ask('Where can I get a cold drink?');

        $this->assertSame(AssistantTool::ProductSearch, $reply->tool);
        $this->assertSame(AnswerOutcome::Answered, $reply->outcome);
        $this->assertSame(
            "Here is what the catalogue has for \"cold\", in Drinks (3):\n"
            ."• Cold Coffee at Cafe Brew: BDT 220.00, available now. Ground Floor, unit G-01. [1]\n"
            ."• Cola Soft Drink at Fresh Mart: BDT 40.00, available now. Ground Floor, unit G-02. Also sold by 1 other shop. [2]\n"
            .'• Mineral Water at Fresh Mart: BDT 20.00, currently unavailable. Ground Floor, unit G-02. [3]',
            $reply->text,
        );
        $this->assertSame([$this->coldCoffee->getKey(), $this->colaAtMart->getKey(), $this->water->getKey()], array_column($reply->recommendations, 'listing_id'));
        $this->assertSame(['Matches "cold", in Drinks', 'Also in Drinks', 'Also in Drinks'], array_column($reply->recommendations, 'reason'));
        $this->assertSame(['Cafe Brew', 'Fresh Mart'], array_column($reply->meta['handoffs'], 'label'));
        $this->assertSame(['q' => 'cold', 'category' => 'drinks'], $reply->meta['filters']);
    }

    public function test_price_stock_and_shop_conditions_choose_the_offer_that_is_shown(): void
    {
        $cheapest = $this->ask('cheapest cola');
        $atCafe = $this->ask('cola at Cafe Brew');
        $inStock = $this->ask('drinks in stock under 50 taka');

        $this->assertSame([$this->colaAtMart->getKey()], array_column($cheapest->recommendations, 'listing_id'));
        $this->assertSame([$this->colaAtCafe->getKey()], array_column($atCafe->recommendations, 'listing_id'));
        $this->assertStringContainsString('Cola Soft Drink at Cafe Brew: BDT 55.00', $atCafe->text);
        $this->assertSame([$this->colaAtMart->getKey()], array_column($inStock->recommendations, 'listing_id'), 'Water costs less but is not in stock.');
        $this->assertSame(['category' => 'drinks', 'max_price' => 50.0, 'available_only' => true], $inStock->meta['filters']);
    }

    public function test_a_request_nothing_matches_exactly_is_relaxed_and_says_so(): void
    {
        $reply = $this->ask('where can I buy a purple notebook');

        $this->assertSame('Nothing in the catalogue matches ""purple notebook"" exactly. The closest, for "notebook" (1):'."\n".'• A5 Notebook at Book Nook: BDT 90.00, available now. First Floor, unit F1-01. [1]', $reply->text);
        $this->assertTrue($reply->meta['relaxed']);
    }

    public function test_a_product_the_mall_does_not_sell_is_never_invented(): void
    {
        $reply = $this->ask('where can I buy a helicopter under 500 taka');

        $this->assertSame(AnswerOutcome::NoAnswer, $reply->outcome);
        $this->assertSame([], $reply->recommendations);
        $this->assertStringNotContainsString('BDT', $reply->text);
    }

    public function test_offers_customers_cannot_buy_are_never_recommended(): void
    {
        $closed = $this->shopIn($this->mall, 'Shut Shop', 'groceries', 'Groceries', 'Suspended.', $this->ground, 'G-09', ['09:00', '22:00'], 'closed');
        $draft = $this->shopIn($this->mall, 'Draft Shop', 'groceries', 'Groceries', 'Not approved.', $this->ground, 'G-10', ['09:00', '22:00'], 'draft');
        $elsewhere = $this->shopIn(Mall::factory()->create(), 'Far Bazaar', 'groceries', 'Groceries', 'Elsewhere.', null, null, ['09:00', '22:00']);
        $this->offer($closed, 'Lemon Soda', 'Drinks', 'Fizzy lemon.', '10.00');
        $this->offer($draft, 'Lemon Soda', 'Drinks', 'Fizzy lemon.', '11.00');
        $this->offer($elsewhere, 'Lemon Soda', 'Drinks', 'Fizzy lemon.', '12.00');
        $this->offer($this->mart, 'Lemon Soda', 'Drinks', 'Fizzy lemon.', '13.00', ['status' => 'archived']);

        $reply = $this->ask('where can I buy lemon soda');

        $this->assertSame(AnswerOutcome::NoAnswer, $reply->outcome);
        $this->assertSame([], $reply->recommendations);
        $this->assertStringNotContainsString('Lemon Soda', $reply->text);
    }

    public function test_best_rated_orders_by_published_reviews_and_unreviewed_offers_are_not_called_rated(): void
    {
        Review::factory()->rated(5)->create(['shop_id' => $this->cafe->getKey(), 'shop_product_id' => $this->colaAtCafe->getKey()]);
        Review::factory()->rated(2)->create(['shop_id' => $this->cafe->getKey(), 'shop_product_id' => $this->coldCoffee->getKey()]);

        $reply = $this->ask('best drinks at Cafe Brew');

        $this->assertSame([$this->colaAtCafe->getKey(), $this->coldCoffee->getKey()], array_column($reply->recommendations, 'listing_id'));
        $this->assertStringContainsString('Cola Soft Drink at Cafe Brew: BDT 55.00, available now. Ground Floor, unit G-01. Rated 5.0 out of 5 by 1 review.', $reply->text);

        $minimum = $this->ask('drinks rated 4 stars or more');

        $this->assertSame([$this->colaAtCafe->getKey()], array_column($minimum->recommendations, 'listing_id'), 'Offers without a review or under 4 stars are left out.');
    }

    public function test_a_bangla_product_request_is_answered_from_the_same_catalogue_in_bangla_phrases(): void
    {
        $reply = $this->ask('৩০০ টাকার মধ্যে কফি কোথায় পাব?');

        $this->assertSame('bn', $reply->language);
        $this->assertSame([$this->coldCoffee->getKey()], array_column($reply->recommendations, 'listing_id'));
        $this->assertStringStartsWith('ক্যাটালগে "coffee", সর্বোচ্চ BDT 300-এর জন্য যা আছে (1টি):', $reply->text);
        $this->assertStringContainsString('Cold Coffee at Cafe Brew: BDT 220.00', $reply->text);
    }
}
