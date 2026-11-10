<?php

namespace App\Modules\ReviewsModeration\DTOs;

/**
 * The rating summary of one target (a shop, or one shop listing), from published reviews only.
 */
final readonly class RatingSummaryData
{
    /**
     * @param  array<int, int>  $distribution  Number of reviews per star, keyed 5 down to 1
     */
    public function __construct(
        public int $reviewCount,
        public ?float $averageRating,
        public array $distribution,
        public int $verifiedCount,
    ) {}

    /**
     * The summary of a target nobody has reviewed yet.
     */
    public static function empty(): self
    {
        return new self(0, null, [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], 0);
    }

    /**
     * Share of the reviews that gave the star rating, 0 to 100.
     */
    public function percentOf(int $star): int
    {
        return $this->reviewCount === 0 ? 0 : (int) round(100 * ($this->distribution[$star] ?? 0) / $this->reviewCount);
    }
}
