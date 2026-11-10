<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ReportReason;
use App\Modules\ReviewsModeration\Enums\ReportStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReport>
 */
class ReviewReportFactory extends Factory
{
    protected $model = ReviewReport::class;

    /**
     * Define the model's default state: an open spam report.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'reporter_user_id' => User::factory(),
            'reason_code' => ReportReason::Spam,
            'details' => null,
            'status' => ReportStatus::Open,
            'resolved_at' => null,
        ];
    }
}
