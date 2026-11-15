<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\DTOs\ProductQueryData;
use App\Modules\ChatbotRag\DTOs\SourceSnippetData;
use App\Modules\ChatbotRag\DTOs\ToolResultData;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Services\Assistant\NavigationHandoff;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\DTOs\RatingSummaryData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The natural-language product finder (Jira ISM-133). The request becomes validated filters, the
 * catalogue is searched with them, and every line of the result is a listing that exists, with the
 * price and availability the catalogue holds at this moment. When nothing matches every word, the
 * search is relaxed step by step and the answer says how; when nothing matches at all, the tool
 * returns nothing rather than a guess.
 */
class ProductTool
{
    public function __construct(
        private ProductQueryParser $parser,
        private CatalogueLookup $catalogue,
        private ReviewSummaryLookup $reviews,
        private NavigationHandoff $handoff,
    ) {}

    public function run(Mall $mall, string $message, string $language): ToolResultData
    {
        $asked = $this->parser->parse($message, $mall);

        if ($asked->isEmpty() || ! $this->isValid($asked)) {
            return ToolResultData::nothing();
        }

        [$products, $used] = $this->search($mall, $asked);
        $limit = (int) config('assistant.max_items', 5);
        $entries = $this->entries($mall, $products, $used, $limit, null);

        // A request that named a category gets the rest of the category after its exact matches.
        if ($used->categorySlug !== null && $used->keywords !== '' && count($entries) < $limit) {
            $rest = $this->catalogue->searchProducts($mall, $used->with('', $used->categorySlug, $used->categoryName)->filters(), $limit * 2)->getCollection()
                ->reject(fn (Product $product): bool => isset($entries[(string) $product->getKey()]));
            $entries += $this->entries($mall, $rest, $used, $limit - count($entries), $used->categoryName);
        }

        $entries = $this->applyRatings(array_values($entries), $used);

        if ($entries === []) {
            return ToolResultData::nothing();
        }

        $sources = [];
        $recommendations = [];
        $handoffs = [];

        foreach ($entries as $index => $entry) {
            $listing = $entry['listing'];
            $sources[] = new SourceSnippetData($index + 1, SourceSnippetData::CATALOGUE, 'Live catalogue', $this->line($entry));
            $recommendations[] = ['listing_id' => (string) $listing->getKey(), 'reason' => $this->reason($entry, $used, $language)];
            $handoffs[(string) $listing->shop_id] ??= $this->handoff->forShop($listing->shop);
        }

        $what = $this->describe($used, $language);

        return new ToolResultData(
            outcome: AnswerOutcome::Answered,
            sources: $sources,
            shape: GenerationRequestData::LISTING,
            lead: $used == $asked
                ? __('Here is what the catalogue has for :what (:count):', ['what' => $what, 'count' => count($entries)], $language)
                : __('Nothing in the catalogue matches ":asked" exactly. The closest, for :what (:count):', ['asked' => $this->describe($asked, $language), 'what' => $what, 'count' => count($entries)], $language),
            recommendations: $recommendations,
            handoffs: array_values($handoffs),
            meta: [
                'filters' => array_filter($used->filters(), fn (mixed $value): bool => $value !== null && $value !== false) + array_filter(['min_rating' => $used->minRating, 'order' => $used->sort]),
                'relaxed' => $used != $asked,
                'notes' => [__('Prices and availability are read from the catalogue each time this page is opened.', [], $language)],
            ],
        );
    }

    /**
     * The catalogue's own rules for a search, applied to the parsed filters before any query runs.
     */
    private function isValid(ProductQueryData $query): bool
    {
        return Validator::make($query->filters(), [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
            'shop' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:100000000', Rule::when($query->minPrice !== null, ['gte:min_price'])],
            'available_only' => ['boolean'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc'])],
        ])->passes();
    }

    /**
     * Searches with every filter, then without some keywords, then by category alone, and returns
     * the first search that finds something together with the query it used.
     *
     * @return array{0: Collection<int, Product>, 1: ProductQueryData}
     */
    private function search(Mall $mall, ProductQueryData $asked): array
    {
        $attempts = [$asked];
        $words = $asked->keywords === '' ? [] : explode(' ', $asked->keywords);

        if (count($words) > 1) {
            usort($words, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

            foreach ($words as $word) {
                $attempts[] = $asked->with($word, $asked->categorySlug, $asked->categoryName);
            }
        }

        if ($asked->categorySlug !== null && $asked->keywords !== '') {
            $attempts[] = $asked->with('', $asked->categorySlug, $asked->categoryName);
        }

        foreach ($attempts as $attempt) {
            $found = $this->catalogue->searchProducts($mall, $attempt->filters(), (int) config('assistant.max_items', 5) * 2)->getCollection();

            if ($found->isNotEmpty()) {
                return [$found, $attempt];
            }
        }

        return [new Collection, $asked];
    }

    /**
     * The best offer of each product under the request's shop, price and stock conditions.
     *
     * @param  iterable<Product>  $products
     * @return array<string, array{listing: ShopProduct, offers: list<ShopProduct>, others: int, also: string|null, rating: RatingSummaryData|null}> Keyed by product id
     */
    private function entries(Mall $mall, iterable $products, ProductQueryData $query, int $limit, ?string $also): array
    {
        $entries = [];

        foreach ($products as $product) {
            if (count($entries) >= $limit) {
                break;
            }

            $offers = $this->catalogue->offersFor($mall, $product)
                ->filter(fn (ShopProduct $offer): bool => ($query->shopSlug === null || $offer->shop->slug === $query->shopSlug)
                    && ($query->minPrice === null || (float) $offer->price_amount >= $query->minPrice)
                    && ($query->maxPrice === null || (float) $offer->price_amount <= $query->maxPrice)
                    && (! $query->availableOnly || $offer->availability_status === 'available'));

            $offers = ($query->sort === 'price_desc' ? $offers->sortByDesc(fn (ShopProduct $offer): float => (float) $offer->price_amount) : $offers)->values();

            if ($offers->isNotEmpty()) {
                $offers->each(fn (ShopProduct $offer) => $offer->variant->setRelation('product', $product));
                $entries[(string) $product->getKey()] = ['listing' => $offers->first(), 'offers' => $offers->all(), 'others' => $offers->count() - 1, 'also' => $also, 'rating' => null];
            }
        }

        return $entries;
    }

    /**
     * Adds each listing's rating summary, drops those under a requested minimum and orders by
     * rating when asked. A listing nobody reviewed has no rating and is never presented as rated.
     *
     * @param  list<array{listing: ShopProduct, offers: list<ShopProduct>, others: int, also: string|null, rating: RatingSummaryData|null}>  $entries
     * @return list<array{listing: ShopProduct, offers: list<ShopProduct>, others: int, also: string|null, rating: RatingSummaryData|null}>
     */
    private function applyRatings(array $entries, ProductQueryData $query): array
    {
        if ($entries === []) {
            return [];
        }

        $byRating = $query->minRating !== null || $query->sort === ProductQueryData::SORT_RATING;
        $average = fn (?RatingSummaryData $summary): float => $summary?->averageRating ?? 0.0;
        $summaries = $this->reviews->forListings(array_values(array_unique(array_merge(...array_map(
            fn (array $entry): array => array_map(fn (ShopProduct $offer): string => (string) $offer->getKey(), $byRating ? $entry['offers'] : [$entry['listing']]),
            $entries,
        )))));

        foreach ($entries as $index => $entry) {
            // When the request is about ratings, the product is shown at its best-rated offer, not its cheapest.
            if ($byRating) {
                foreach ($entry['offers'] as $offer) {
                    if ($average($summaries[(string) $offer->getKey()] ?? null) > $average($summaries[(string) $entries[$index]['listing']->getKey()] ?? null)) {
                        $entries[$index]['listing'] = $offer;
                    }
                }
            }

            $entries[$index]['rating'] = $summaries[(string) $entries[$index]['listing']->getKey()] ?? null;
        }

        if ($query->minRating !== null) {
            $entries = array_values(array_filter($entries, fn (array $entry): bool => ($entry['rating']?->averageRating ?? 0.0) >= $query->minRating));
        }

        if ($query->sort === ProductQueryData::SORT_RATING) {
            usort($entries, fn (array $a, array $b): int => [$b['rating']?->averageRating ?? 0.0, $b['rating']?->reviewCount ?? 0] <=> [$a['rating']?->averageRating ?? 0.0, $a['rating']?->reviewCount ?? 0]);
        }

        return $entries;
    }

    /**
     * @param  array{listing: ShopProduct, offers: list<ShopProduct>, others: int, also: string|null, rating: RatingSummaryData|null}  $entry
     */
    private function line(array $entry): string
    {
        $listing = $entry['listing'];
        $product = $listing->variant->product;
        $units = $listing->shop->units->where('status', '!=', 'archived');
        $option = $listing->variant->label === 'Standard' ? '' : ' ('.$listing->variant->label.')';

        $line = $product->name.$option.' at '.$listing->shop->name.': '.$listing->currency.' '.number_format((float) $listing->price_amount, 2).', '
            .($listing->availability_status === 'available' ? 'available now' : 'currently unavailable').'.';

        if ($units->isNotEmpty()) {
            $line .= ' '.$units->map(fn (ShopUnit $unit): string => $unit->floor->name.', unit '.$unit->unit_code)->implode('; ').'.';
        }

        if ($entry['others'] > 0) {
            $line .= ' Also sold by '.$entry['others'].' other '.($entry['others'] === 1 ? 'shop' : 'shops').'.';
        }

        if ($entry['rating'] !== null && $entry['rating']->reviewCount > 0) {
            $line .= ' Rated '.number_format((float) $entry['rating']->averageRating, 1).' out of 5 by '.$entry['rating']->reviewCount.' '.($entry['rating']->reviewCount === 1 ? 'review' : 'reviews').'.';
        }

        return $line;
    }

    /**
     * @param  array{listing: ShopProduct, offers: list<ShopProduct>, others: int, also: string|null, rating: RatingSummaryData|null}  $entry
     */
    private function reason(array $entry, ProductQueryData $query, string $language): string
    {
        if ($entry['also'] !== null) {
            return __('Also in :category', ['category' => $entry['also']], $language);
        }

        $what = $this->describe($query, $language);

        return $entry['others'] > 0
            ? __('Matches :what; best of :count offers', ['what' => $what, 'count' => $entry['others'] + 1], $language)
            : __('Matches :what', ['what' => $what], $language);
    }

    /**
     * The filters in words, so the visitor sees how the request was understood.
     */
    private function describe(ProductQueryData $query, string $language): string
    {
        $parts = [];
        $money = fn (float $amount): string => 'BDT '.rtrim(rtrim(number_format($amount, 2), '0'), '.');

        if ($query->keywords !== '') {
            $parts[] = '"'.$query->keywords.'"';
        }

        if ($query->categoryName !== null) {
            $parts[] = __('in :category', ['category' => $query->categoryName], $language);
        }

        if ($query->shopName !== null) {
            $parts[] = __('at :shop', ['shop' => $query->shopName], $language);
        }

        if ($query->minPrice !== null && $query->maxPrice !== null) {
            $parts[] = __(':from to :to', ['from' => $money($query->minPrice), 'to' => $money($query->maxPrice)], $language);
        } elseif ($query->maxPrice !== null) {
            $parts[] = __('up to :amount', ['amount' => $money($query->maxPrice)], $language);
        } elseif ($query->minPrice !== null) {
            $parts[] = __('from :amount', ['amount' => $money($query->minPrice)], $language);
        }

        if ($query->availableOnly) {
            $parts[] = __('in stock only', [], $language);
        }

        if ($query->minRating !== null) {
            $parts[] = __('rated :rating or more', ['rating' => rtrim(rtrim(number_format($query->minRating, 1), '0'), '.')], $language);
        }

        $order = match ($query->sort) {
            'price_asc' => __('cheapest first', [], $language),
            'price_desc' => __('most expensive first', [], $language),
            ProductQueryData::SORT_RATING => __('best rated first', [], $language),
            default => null,
        };

        if ($order !== null) {
            $parts[] = $order;
        }

        return implode(', ', $parts);
    }
}
