<?php

namespace App\Modules\ReviewsModeration\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ReportReason;
use App\Modules\ReviewsModeration\Enums\ReportStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Lets a signed-in customer flag a published review for the mall's moderators.
 *
 * The reporter's identity is stored for abuse control but is never shown to the review's
 * author, the shop, or in the moderation queue.
 */
class ReviewReportService
{
    public function __construct(private AuditLogger $audit) {}

    public function report(User $reporter, Review $review, ReportReason $reason, ?string $details = null): ReviewReport
    {
        if ($review->author_user_id === $reporter->getKey()) {
            throw new BusinessRuleViolation('report.own_review', __('You cannot report your own review. Withdraw it instead.'));
        }

        if (! $review->status->isPublic()) {
            throw new BusinessRuleViolation('report.review_not_public', __('This review is not visible, so it cannot be reported.'));
        }

        $details = $details === null || trim($details) === '' ? null : trim($details);

        if ($details !== null && mb_strlen($details) > ReviewPolicy::REPORT_DETAILS_MAX) {
            throw new BusinessRuleViolation('report.invalid_details', __('Keep the explanation under :max characters.', ['max' => ReviewPolicy::REPORT_DETAILS_MAX]));
        }

        if ($reason === ReportReason::Other && $details === null) {
            throw new BusinessRuleViolation('report.details_required', __('Please tell us what is wrong with this review.'));
        }

        if (ReviewReport::query()->where('review_id', $review->getKey())->where('reporter_user_id', $reporter->getKey())->exists()) {
            throw new BusinessRuleViolation('report.already_reported', __('You already reported this review. A moderator will look at it.'));
        }

        try {
            $report = ReviewReport::query()->create([
                'review_id' => $review->getKey(),
                'reporter_user_id' => $reporter->getKey(),
                'reason_code' => $reason,
                'details' => $details,
                'status' => ReportStatus::Open,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('report.already_reported', __('You already reported this review. A moderator will look at it.'));
        }

        $this->audit->record('review.reported', $report, ['review_id' => $review->getKey(), 'reason' => $reason->value], $review->shop->mall_id, $review->shop_id);

        return $report;
    }
}
