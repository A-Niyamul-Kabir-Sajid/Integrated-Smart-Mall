<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ModerationActionType;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\ModerationAction;
use App\Modules\ReviewsModeration\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationAction>
 */
class ModerationActionFactory extends Factory
{
    protected $model = ModerationAction::class;

    /**
     * Define the model's default state: a moderator hiding a published review.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'review_report_id' => null,
            'actor_user_id' => User::factory(),
            'action' => ModerationActionType::Hide,
            'reason' => fake()->sentence(),
            'previous_status' => ReviewStatus::Published,
            'resulting_status' => ReviewStatus::Hidden,
        ];
    }
}
