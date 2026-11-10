<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ReplyStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReply>
 */
class ReviewReplyFactory extends Factory
{
    protected $model = ReviewReply::class;

    /**
     * Define the model's default state: a published reply.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'author_user_id' => User::factory(),
            'body' => fake()->sentence(),
            'status' => ReplyStatus::Published,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReplyStatus::Hidden]);
    }
}
