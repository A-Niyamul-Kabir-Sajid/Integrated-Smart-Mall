<?php

namespace App\Modules\ReviewsModeration\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ReviewsModeration\Enums\ModerationActionType;
use App\Modules\ReviewsModeration\Enums\ReportStatus;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\ModerationAction;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The moderation desk of one mall: a queue of reported reviews and the decisions moderators
 * take on them. Only administrators of the review's own mall may moderate it
 * (administer-mall), every decision needs a written reason, and each decision is recorded
 * in the review's append-only history in the same transaction that changes the review
 * and its reports.
 */
class ModerationService
{
    public const PER_PAGE = 15;

    public function __construct(private AuditLogger $audit) {}

    /**
     * Reviews of the mall with open reports, most reported first. The reporters are not loaded:
     * the queue shows what was reported and why, never who reported.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function reportedReviews(User $moderator, Mall $mall, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        Gate::forUser($moderator)->authorize(Ability::AdministerMall->value, $mall);

        return $this->mallReviews($mall)
            ->whereHas('reports', fn (Builder $reports) => $reports->open())
            ->withCount(['reports as open_reports_count' => fn (Builder $reports) => $reports->open()])
            ->with(['reports' => fn ($reports) => $reports->open()
                ->select(['review_report_id', 'review_id', 'reason_code', 'details', 'status', 'created_at'])
                ->orderBy('created_at')])
            ->orderByDesc('open_reports_count')
            ->orderBy('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Reviews of the mall that moderators hid, newest decision first, so a decision can be reversed.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function hiddenReviews(User $moderator, Mall $mall, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        Gate::forUser($moderator)->authorize(Ability::AdministerMall->value, $mall);

        return $this->mallReviews($mall)
            ->where('status', ReviewStatus::Hidden->value)
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Hides a visible review. Open reports about it are resolved by this decision.
     */
    public function hide(User $moderator, Review $review, string $reason): Review
    {
        return $this->changeStatus($moderator, $review, ModerationActionType::Hide, ReviewStatus::Hidden, $reason, resolveReports: true);
    }

    /**
     * Puts a hidden review back on public view.
     */
    public function restore(User $moderator, Review $review, string $reason): Review
    {
        return $this->changeStatus($moderator, $review, ModerationActionType::Restore, ReviewStatus::Published, $reason, resolveReports: false, onlyFrom: ReviewStatus::Hidden);
    }

    /**
     * Approves a review that was waiting for approval.
     */
    public function publish(User $moderator, Review $review, string $reason): Review
    {
        return $this->changeStatus($moderator, $review, ModerationActionType::Publish, ReviewStatus::Published, $reason, resolveReports: false, onlyFrom: ReviewStatus::Pending);
    }

    /**
     * Rejects one report: the review stays as it is.
     */
    public function dismissReport(User $moderator, ReviewReport $report, string $reason): ReviewReport
    {
        $review = $report->review;
        $this->authorize($moderator, $review);
        $reason = $this->reason($reason);

        if ($report->status !== ReportStatus::Open) {
            throw new BusinessRuleViolation('moderation.report_closed', __('This report was already handled.'));
        }

        DB::transaction(function () use ($moderator, $review, $report, $reason): void {
            $report->update(['status' => ReportStatus::Dismissed, 'resolved_at' => now()]);
            $this->record($moderator, $review, ModerationActionType::DismissReport, $review->status, $review->status, $reason, $report);
        });

        return $report;
    }

    /**
     * Rejects every open report about a review at once.
     */
    public function dismissOpenReports(User $moderator, Review $review, string $reason): int
    {
        $this->authorize($moderator, $review);
        $reason = $this->reason($reason);
        $reports = $review->reports()->open()->get();

        if ($reports->isEmpty()) {
            throw new BusinessRuleViolation('moderation.nothing_to_dismiss', __('This review has no open reports.'));
        }

        DB::transaction(function () use ($moderator, $review, $reports, $reason): void {
            foreach ($reports as $report) {
                $report->update(['status' => ReportStatus::Dismissed, 'resolved_at' => now()]);
                $this->record($moderator, $review, ModerationActionType::DismissReport, $review->status, $review->status, $reason, $report);
            }
        });

        return $reports->count();
    }

    /**
     * Removes the verified-purchase badge, for example after finding the purchase was not genuine.
     * The review stays; the evidence is kept as history.
     */
    public function revokeVerification(User $moderator, Review $review, string $reason): Review
    {
        $this->authorize($moderator, $review);
        $reason = $this->reason($reason);

        DB::transaction(function () use ($moderator, $review, $reason): void {
            $verification = $review->verification()->lockForUpdate()->first();

            if ($verification === null || $verification->revoked_at !== null) {
                throw new BusinessRuleViolation('moderation.not_verified', __('This review has no active purchase verification.'));
            }

            $verification->update(['revoked_at' => now(), 'revocation_reason' => $reason]);
            $this->record($moderator, $review, ModerationActionType::RevokeVerification, $review->status, $review->status, $reason);
        });

        return $review;
    }

    private function changeStatus(User $moderator, Review $review, ModerationActionType $action, ReviewStatus $to, string $reason, bool $resolveReports, ?ReviewStatus $onlyFrom = null): Review
    {
        $this->authorize($moderator, $review);
        $reason = $this->reason($reason);

        DB::transaction(function () use ($moderator, $review, $action, $to, $reason, $resolveReports, $onlyFrom): void {
            $locked = Review::query()->lockForUpdate()->findOrFail($review->getKey());
            $from = $locked->status;

            if (($onlyFrom !== null && $from !== $onlyFrom) || ! in_array($to, $from->allowedTransitions(), true)) {
                throw new BusinessRuleViolation('moderation.invalid_transition', __('A :from review cannot be set to :to.', ['from' => $from->value, 'to' => $to->value]));
            }

            $locked->update(['status' => $to]);

            if ($resolveReports) {
                $locked->reports()->open()->get()->each(fn (ReviewReport $report) => $report->update(['status' => ReportStatus::Resolved, 'resolved_at' => now()]));
            }

            $this->record($moderator, $locked, $action, $from, $to, $reason);
            $review->setRawAttributes($locked->getAttributes(), true);
        });

        return $review;
    }

    private function record(User $moderator, Review $review, ModerationActionType $action, ReviewStatus $from, ReviewStatus $to, string $reason, ?ReviewReport $report = null): void
    {
        $entry = ModerationAction::query()->create([
            'review_id' => $review->getKey(),
            'review_report_id' => $report?->getKey(),
            'actor_user_id' => $moderator->getKey(),
            'action' => $action,
            'reason' => $reason,
            'previous_status' => $from,
            'resulting_status' => $to,
        ]);

        $shop = $review->shop;
        $this->audit->record('review.moderated', $entry, ['review_id' => $review->getKey(), 'action' => $action->value, 'from' => $from->value, 'to' => $to->value], $shop->mall_id, $shop->getKey());
    }

    private function authorize(User $moderator, Review $review): void
    {
        Gate::forUser($moderator)->authorize(Ability::AdministerMall->value, $review->shop->mall);
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > ReviewPolicy::REASON_MAX) {
            throw new BusinessRuleViolation('moderation.reason_required', __('Write the reason for this decision (up to :max characters).', ['max' => ReviewPolicy::REASON_MAX]));
        }

        return $reason;
    }

    /**
     * @return Builder<Review>
     */
    private function mallReviews(Mall $mall): Builder
    {
        return Review::query()
            ->whereHas('shop', fn (Builder $shops) => $shops->where('mall_id', $mall->getKey()))
            ->with([
                'shop:shop_id,mall_id,name,slug',
                'author:user_id,display_name',
                'listing.variant.product',
                'verification',
                'moderationActions.actor:user_id,display_name',
            ]);
    }
}
