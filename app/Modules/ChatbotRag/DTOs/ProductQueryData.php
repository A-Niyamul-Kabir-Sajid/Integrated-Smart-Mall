<?php

namespace App\Modules\ChatbotRag\DTOs;

/**
 * A product request in the terms the catalogue understands. Every field was read from the question
 * by fixed rules and checked against the catalogue's own validation; nothing here is free text
 * passed to a query.
 */
final readonly class ProductQueryData
{
    public const SORT_RATING = 'rating';

    /**
     * @param  string  $keywords  At most five words that must appear in the product name, brand or category
     * @param  string|null  $sort  "price_asc", "price_desc", "rating" or null for the catalogue's order
     */
    public function __construct(
        public string $keywords = '',
        public ?string $categorySlug = null,
        public ?string $categoryName = null,
        public ?string $shopSlug = null,
        public ?string $shopName = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public bool $availableOnly = false,
        public ?string $sort = null,
        public ?float $minRating = null,
    ) {}

    /**
     * True when the question named nothing to look for.
     */
    public function isEmpty(): bool
    {
        return $this->keywords === '' && $this->categorySlug === null && $this->shopSlug === null;
    }

    /**
     * The filters as CatalogueLookup::searchProducts() takes them.
     *
     * @return array{q: string|null, category: string|null, shop: string|null, min_price: float|null, max_price: float|null, available_only: bool, sort: string|null}
     */
    public function filters(): array
    {
        return [
            'q' => $this->keywords === '' ? null : $this->keywords,
            'category' => $this->categorySlug,
            'shop' => $this->shopSlug,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'available_only' => $this->availableOnly,
            'sort' => in_array($this->sort, ['price_asc', 'price_desc'], true) ? $this->sort : null,
        ];
    }

    public function with(string $keywords, ?string $categorySlug, ?string $categoryName): self
    {
        return new self($keywords, $categorySlug, $categoryName, $this->shopSlug, $this->shopName, $this->minPrice, $this->maxPrice, $this->availableOnly, $this->sort, $this->minRating);
    }
}
