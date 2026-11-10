<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\TokenRedemption;
use App\Modules\PurchaseVerification\Models\VerificationToken;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state: a published shop review.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_user_id' => User::factory(),
            'shop_id' => Shop::factory(),
            'shop_product_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'status' => ReviewStatus::Published,
        ];
    }

    /**
     * A review of one shop listing; the shop is the listing's shop.
     */
    public function forListing(ShopProduct $listing): static
    {
        return $this->state(fn (array $attributes) => ['shop_id' => $listing->shop_id, 'shop_product_id' => $listing->getKey()]);
    }

    public function rated(int $rating): static
    {
        return $this->state(fn (array $attributes) => ['rating' => $rating]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Pending]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Hidden]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Withdrawn]);
    }

    /**
     * Binds the review to a redeemed purchase credential: it builds a completed sale of the
     * reviewed listing for the author, a credential for that line and its redemption.
     * Use with forListing(); a shop review cannot be verified.
     */
    public function verified(): static
    {
        return $this->afterCreating(function (Review $review): void {
            $purchase = Purchase::factory()->for($review->shop)->forBuyer($review->author)->withLine($review->listing)->create();
            $token = VerificationToken::factory()->create(['purchase_item_id' => $purchase->items()->first()->getKey()]);

            TokenRedemption::query()->create([
                'verification_token_id' => $token->getKey(),
                'user_id' => $review->author_user_id,
                'redeemed_at' => now(),
            ]);

            ReviewVerification::query()->create([
                'review_id' => $review->getKey(),
                'verification_token_id' => $token->getKey(),
                'verified_at' => now(),
            ]);
        });
    }
}
