<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Modules\ChatbotRag\DTOs\ProductQueryData;
use App\Modules\ChatbotRag\Services\Retrieval\QueryExpander;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;

/**
 * Turns a product request written in plain words into catalogue filters (Jira ISM-133): a price
 * range, in stock only, an order, a minimum rating, a shop of this mall, a product category and at
 * most five keywords. Fixed rules do the reading, so the same sentence always gives the same
 * filters and a sentence can never become anything but these filters.
 */
class ProductQueryParser
{
    public const MAX_KEYWORDS = 5;

    private const CURRENCY = '(?:bdt|tk|taka|৳|টাকার|টাকা)';

    private const NUMBER = '(\d[\d,]*(?:\.\d+)?k?)';

    /**
     * Words of a request that describe the asking, not the product.
     */
    private const NOISE = [
        'buy', 'purchase', 'price', 'prices', 'priced', 'cost', 'costs', 'cheap', 'cheaper', 'expensive', 'sell', 'sells',
        'selling', 'sold', 'stock', 'shop', 'shops', 'store', 'stores', 'product', 'products', 'item', 'items', 'offer',
        'offers', 'recommend', 'suggest', 'good', 'nice', 'bdt', 'tk', 'taka', 'money', 'budget', 'range', 'between',
        'under', 'below', 'above', 'over', 'around', 'first', 'only', 'sale', 'here', 'today', 'now', 'available',
        'দাম', 'কিনতে', 'কিনব', 'কিনবো', 'টাকা', 'টাকার', 'মধ্যে', 'দোকান', 'দোকানে', 'দরকার', 'লাগবে', 'ভালো', 'বিক্রি',
    ];

    public function __construct(
        private TextAnalyzer $analyzer,
        private QueryExpander $expander,
        private CatalogueLookup $catalogue,
        private DirectoryLookup $directory,
    ) {}

    public function parse(string $message, Mall $mall): ProductQueryData
    {
        $text = ' '.$this->analyzer->normalise($message).' ';

        [$minPrice, $maxPrice] = $this->prices($text);
        $minRating = $this->rating($text);
        $sort = $this->sort($text);
        $availableOnly = $this->take($text, '/\b(in stock|available now|available|availability)\b|স্টকে আছে|স্টকে/u') !== null;
        [$shopSlug, $shopName] = $this->shop($text, $mall);

        $noise = array_fill_keys(array_map($this->analyzer->normalise(...), self::NOISE), true);
        $words = array_values(array_filter(
            $this->analyzer->contentWords($text),
            fn (string $word): bool => ! isset($noise[$word]) && preg_match('/^\d+$/', $word) !== 1,
        ));

        [$categorySlug, $categoryName, $words] = $this->category($words);

        return new ProductQueryData(
            keywords: implode(' ', array_slice(array_values(array_unique(array_map($this->catalogueWord(...), $words))), 0, self::MAX_KEYWORDS)),
            categorySlug: $categorySlug,
            categoryName: $categoryName,
            shopSlug: $shopSlug,
            shopName: $shopName,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            availableOnly: $availableOnly,
            sort: $sort,
            minRating: $minRating,
        );
    }

    /**
     * Reads "under 500", "between 200 and 500 taka", "at least 1k", "৫০০ টাকার মধ্যে" and removes
     * them from the text.
     *
     * @return array{0: float|null, 1: float|null} Minimum and maximum price
     */
    private function prices(string &$text): array
    {
        $currency = self::CURRENCY;
        $number = self::NUMBER;

        if (($match = $this->take($text, "/(?:between|from)\s+(?:{$currency}\s*)?{$number}\s*(?:{$currency}\s*)?(?:and|to|-|–)\s*(?:{$currency}\s*)?{$number}(?:\s*{$currency})?/u")) !== null
            || ($match = $this->take($text, "/{$number}\s*(?:-|–|to)\s*{$number}\s*{$currency}/u")) !== null) {
            $range = [$this->amount($match[1]), $this->amount($match[2])];

            return [min($range), max($range)];
        }

        $max = $this->take($text, "/(?:under|below|less than|cheaper than|at most|maximum|max|up to|upto|within|not more than|no more than|budget of|budget is|budget)\s+(?:{$currency}\s*)?{$number}(?:\s*{$currency})?/u")
            ?? $this->take($text, "/{$number}\s*{$currency}\s*(?:মধ্যে|নিচে|কমে|কম)/u");
        $min = $this->take($text, "/(?:over|above|more than|at least|minimum|min|starting from|not less than)\s+(?:{$currency}\s*)?{$number}(?:\s*{$currency})?/u")
            ?? $this->take($text, "/{$number}\s*{$currency}\s*(?:উপরে|বেশি)/u");

        return [$min === null ? null : $this->amount($min[1]), $max === null ? null : $this->amount($max[1])];
    }

    private function amount(string $written): float
    {
        $written = str_replace(',', '', $written);

        return str_ends_with($written, 'k') ? (float) rtrim($written, 'k') * 1000 : (float) $written;
    }

    /**
     * "4 stars", "4+ stars", "rated 4.5 or more".
     */
    private function rating(string &$text): ?float
    {
        $match = $this->take($text, '/(?:rated\s+)?([1-5](?:\.\d)?)\s*(?:\+|plus)?\s*stars?(?:\s*(?:and|or)\s*(?:above|more|up|higher|better))?/u')
            ?? $this->take($text, '/rated\s+([1-5](?:\.\d)?)\s*(?:\+|plus|(?:and|or)\s*(?:above|more|up|higher|better))/u');

        return $match === null ? null : min(5.0, (float) $match[1]);
    }

    private function sort(string &$text): ?string
    {
        if ($this->take($text, '/\b(cheapest|lowest price|lowest priced|least expensive|low to high|most affordable)\b|সস্তা/u') !== null) {
            return 'price_asc';
        }

        if ($this->take($text, '/\b(most expensive|highest price|highest priced|high to low|priciest)\b/u') !== null) {
            return 'price_desc';
        }

        if ($this->take($text, '/\b(top rated|top-rated|best rated|highest rated|best reviewed|most popular|best)\b/u') !== null) {
            return ProductQueryData::SORT_RATING;
        }

        return null;
    }

    /**
     * The listed shop of this mall whose name the request contains; the longest name wins.
     *
     * @return array{0: string|null, 1: string|null} Slug and name
     */
    private function shop(string &$text, Mall $mall): array
    {
        $shops = $this->directory->listedShops((string) $mall->getKey())->sortByDesc(fn ($shop): int => mb_strlen($shop->name));

        foreach ($shops as $shop) {
            $name = $this->analyzer->normalise($shop->name);

            if (mb_strlen($name) >= 3 && $this->take($text, '/(?<![\p{L}\p{M}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{M}\p{N}])/u') !== null) {
                return [$shop->slug, $shop->name];
            }
        }

        return [null, null];
    }

    /**
     * The category the words name, with those words taken out. A category matches when every word
     * of its name is asked for (in any wording: "beverages" asks for "Drinks"); for a name such as
     * "Snacks & Meals" either side is enough.
     *
     * @param  list<string>  $words
     * @return array{0: string|null, 1: string|null, 2: list<string>} Slug, name and the remaining words
     */
    private function category(array $words): array
    {
        $stemsByWord = [];

        foreach ($words as $word) {
            $stemsByWord[$word] = array_map($this->analyzer->stem(...), [$word, ...$this->expander->alternatives($word)]);
        }

        $best = null;

        foreach ($this->catalogue->categories() as $category) {
            $nameStems = $this->analyzer->terms($category->name);

            if ($nameStems === []) {
                continue;
            }

            $matchedWords = array_keys(array_filter($stemsByWord, fn (array $stems): bool => array_intersect($stems, $nameStems) !== []));
            $covered = count(array_filter($nameStems, fn (string $stem): bool => array_filter($stemsByWord, fn (array $stems): bool => in_array($stem, $stems, true)) !== []));
            $eitherSide = preg_match('/&|\band\b/i', $category->name) === 1;

            if ($covered === 0 || (! $eitherSide && $covered < count($nameStems))) {
                continue;
            }

            if ($best === null || $covered > $best['covered']) {
                $best = ['covered' => $covered, 'slug' => $category->slug, 'name' => $category->name, 'words' => $matchedWords];
            }
        }

        if ($best === null) {
            return [null, null, $words];
        }

        return [$best['slug'], $best['name'], array_values(array_diff($words, $best['words']))];
    }

    /**
     * The catalogue is written in English: a Bangla word with a known English equivalent is searched
     * by that equivalent.
     */
    private function catalogueWord(string $word): string
    {
        if (preg_match('/^[\x00-\x7F]+$/', $word) === 1) {
            return $word;
        }

        foreach ($this->expander->alternatives($word) as $alternative) {
            if (preg_match('/^[a-z0-9]+$/', $alternative) === 1) {
                return $alternative;
            }
        }

        return $word;
    }

    /**
     * Finds the pattern in the text and removes what it matched.
     *
     * @return array<int, string>|null The match with its groups, or null
     */
    private function take(string &$text, string $pattern): ?array
    {
        if (preg_match($pattern, $text, $match) !== 1) {
            return null;
        }

        $text = str_replace($match[0], ' ', $text);

        return $match;
    }
}
