<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\Enums\ModerationActionType;
use App\Modules\ReviewsModeration\Enums\ReportReason;
use App\Modules\ReviewsModeration\Enums\ReportStatus;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\ModerationAction;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use App\Modules\ReviewsModeration\Services\ModerationService;
use App\Modules\ReviewsModeration\Services\ReviewReportService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAndModerationServiceTest extends TestCase
{
    use CreatesReviewFixtures, RefreshDatabase;

    private function reports(): ReviewReportService
    {
        return app(ReviewReportService::class);
    }

    private function moderation(): ModerationService
    {
        return app(ModerationService::class);
    }

    public function test_a_customer_reports_a_published_review_once(): void
    {
        $review = Review::factory()->create();
        $reporter = User::factory()->create();

        $report = $this->reports()->report($reporter, $review, ReportReason::Spam, '  Selling something  ');

        $this->assertSame(ReportStatus::Open, $report->status);
        $this->assertSame('Selling something', $report->details);
        $this->assertSame($reporter->getKey(), $report->reporter_user_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'review.reported')->count());
        $this->assertRuleViolated('report.already_reported', fn () => $this->reports()->report($reporter, $review, ReportReason::Abuse));
        $this->assertSame(1, ReviewReport::query()->count());
    }

    public function test_reporting_rules(): void
    {
        $review = Review::factory()->create();
        $reporter = User::factory()->create();

        $this->assertRuleViolated('report.own_review', fn () => $this->reports()->report($review->author, $review, ReportReason::Spam));
        $this->assertRuleViolated('report.review_not_public', fn () => $this->reports()->report($reporter, Review::factory()->hidden()->create(), ReportReason::Spam));
        $this->assertRuleViolated('report.review_not_public', fn () => $this->reports()->report($reporter, Review::factory()->withdrawn()->create(), ReportReason::Spam));
        $this->assertRuleViolated('report.details_required', fn () => $this->reports()->report($reporter, $review, ReportReason::Other, '  '));
        $this->assertRuleViolated('report.invalid_details', fn () => $this->reports()->report($reporter, $review, ReportReason::Spam, str_repeat('a', 1001)));
        $this->assertSame(0, ReviewReport::query()->count());
    }

    public function test_a_mall_moderator_hides_a_reported_review_and_the_decision_is_traceable(): void
    {
        $review = Review::factory()->rated(1)->create();
        Review::factory()->for($review->shop)->rated(5)->create();
        ReviewReport::factory()->for($review)->create();
        ReviewReport::factory()->for($review)->create();
        $moderator = $this->moderatorOf($review->shop);
        $this->actingAs($moderator);

        $this->moderation()->hide($moderator, $review, 'Abusive language towards staff.');

        $this->assertSame(ReviewStatus::Hidden, $review->fresh()->status);
        $this->assertSame(2, ReviewReport::query()->where('status', ReportStatus::Resolved)->whereNotNull('resolved_at')->count());
        $action = ModerationAction::query()->sole();
        $this->assertSame(ModerationActionType::Hide, $action->action);
        $this->assertSame($moderator->getKey(), $action->actor_user_id);
        $this->assertSame('Abusive language towards staff.', $action->reason);
        $this->assertSame([ReviewStatus::Published, ReviewStatus::Hidden], [$action->previous_status, $action->resulting_status]);
        $this->assertSame(5.0, app(ReviewSummaryLookup::class)->forShop($review->shop_id)->averageRating, 'The hidden review leaves the aggregate at once.');
        $this->assertSame($moderator->getKey(), AuditLog::query()->where('action', 'review.moderated')->sole()->actor_id);
    }

    public function test_only_administrators_of_the_reviews_own_mall_can_moderate(): void
    {
        $review = Review::factory()->create();
        $report = ReviewReport::factory()->for($review)->create();
        $outsiders = [
            User::factory()->create(),
            $review->author,
            $this->staffOf($review->shop),
            $this->moderatorOf(Shop::factory()->create()),
        ];

        foreach ($outsiders as $user) {
            foreach ([
                fn () => $this->moderation()->hide($user, $review, 'No right'),
                fn () => $this->moderation()->restore($user, $review, 'No right'),
                fn () => $this->moderation()->dismissReport($user, $report, 'No right'),
                fn () => $this->moderation()->dismissOpenReports($user, $review, 'No right'),
                fn () => $this->moderation()->revokeVerification($user, $review, 'No right'),
                fn () => $this->moderation()->reportedReviews($user, $review->shop->mall),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('Expected the moderation action to be refused.');
                } catch (AuthorizationException) {
                    $this->assertSame(ReviewStatus::Published, $review->fresh()->status);
                }
            }
        }

        $this->assertSame(0, ModerationAction::query()->count());
        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
    }

    public function test_every_decision_needs_a_reason(): void
    {
        $review = Review::factory()->create();
        $moderator = $this->moderatorOf($review->shop);

        $this->assertRuleViolated('moderation.reason_required', fn () => $this->moderation()->hide($moderator, $review, '   '));
        $this->assertRuleViolated('moderation.reason_required', fn () => $this->moderation()->hide($moderator, $review, str_repeat('a', 1001)));
        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);
        $this->assertSame(0, ModerationAction::query()->count());
    }

    public function test_restoring_publishing_and_invalid_transitions(): void
    {
        $shop = Shop::factory()->create();
        $moderator = $this->moderatorOf($shop);
        $hidden = Review::factory()->for($shop)->hidden()->create();
        $pending = Review::factory()->for($shop)->pending()->create();
        $published = Review::factory()->for($shop)->create();
        $withdrawn = Review::factory()->for($shop)->withdrawn()->create();

        $this->moderation()->restore($moderator, $hidden, 'Reviewed again, it is fine.');
        $this->moderation()->publish($moderator, $pending, 'Approved.');
        $this->assertSame(ReviewStatus::Published, $hidden->fresh()->status);
        $this->assertSame(ReviewStatus::Published, $pending->fresh()->status);

        $this->assertRuleViolated('moderation.invalid_transition', fn () => $this->moderation()->restore($moderator, $published, 'Not hidden'));
        $this->assertRuleViolated('moderation.invalid_transition', fn () => $this->moderation()->publish($moderator, $published, 'Not pending'));
        $this->assertRuleViolated('moderation.invalid_transition', fn () => $this->moderation()->hide($moderator, $withdrawn, 'Already gone'));
        $this->assertRuleViolated('moderation.invalid_transition', fn () => $this->moderation()->hide($moderator, Review::factory()->for($shop)->hidden()->create(), 'Twice'));
        $this->assertSame(2, ModerationAction::query()->count());
    }

    public function test_dismissing_reports_keeps_the_review_and_records_each_report(): void
    {
        $review = Review::factory()->create();
        $first = ReviewReport::factory()->for($review)->create();
        ReviewReport::factory()->for($review)->create();
        $moderator = $this->moderatorOf($review->shop);

        $this->moderation()->dismissReport($moderator, $first, 'Not a policy violation.');
        $this->assertSame(ReportStatus::Dismissed, $first->fresh()->status);
        $this->assertRuleViolated('moderation.report_closed', fn () => $this->moderation()->dismissReport($moderator, $first->fresh(), 'Again'));

        $this->assertSame(1, $this->moderation()->dismissOpenReports($moderator, $review, 'Same as the other one.'));
        $this->assertRuleViolated('moderation.nothing_to_dismiss', fn () => $this->moderation()->dismissOpenReports($moderator, $review, 'Nothing left'));

        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);
        $actions = ModerationAction::query()->where('review_id', $review->getKey())->get();
        $this->assertCount(2, $actions);
        $this->assertSame([ModerationActionType::DismissReport], $actions->pluck('action')->unique()->all());
        $this->assertEqualsCanonicalizing([$first->getKey(), ReviewReport::query()->where('review_report_id', '!=', $first->getKey())->value('review_report_id')], $actions->pluck('review_report_id')->all());
    }

    public function test_a_moderator_can_remove_the_verified_badge_but_not_twice(): void
    {
        $listing = ShopProduct::factory()->create();
        $review = Review::factory()->forListing($listing)->verified()->create();
        $moderator = $this->moderatorOf($listing->shop);

        $this->moderation()->revokeVerification($moderator, $review, 'The receipt was forged.');

        $this->assertFalse($review->fresh()->isVerified());
        $this->assertSame('The receipt was forged.', $review->fresh()->verification->revocation_reason);
        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);
        $this->assertSame(ModerationActionType::RevokeVerification, ModerationAction::query()->sole()->action);
        $this->assertRuleViolated('moderation.not_verified', fn () => $this->moderation()->revokeVerification($moderator, $review->fresh(), 'Again'));
        $this->assertRuleViolated('moderation.not_verified', fn () => $this->moderation()->revokeVerification($moderator, Review::factory()->for($listing->shop)->create(), 'Never verified'));
    }

    public function test_the_queue_lists_reported_reviews_of_the_mall_most_reported_first_without_reporters(): void
    {
        $shop = Shop::factory()->create();
        $moderator = $this->moderatorOf($shop);
        $few = Review::factory()->for($shop)->create();
        $many = Review::factory()->for($shop)->create();
        ReviewReport::factory()->for($few)->create();
        ReviewReport::factory()->for($many)->count(3)->create();
        ReviewReport::factory()->for(Review::factory()->for($shop)->create())->create(['status' => ReportStatus::Dismissed]);
        ReviewReport::factory()->for(Review::factory()->create())->create();

        $queue = $this->moderation()->reportedReviews($moderator, $shop->mall);

        $this->assertSame([$many->getKey(), $few->getKey()], $queue->pluck('review_id')->all());
        $this->assertSame(3, $queue->first()->open_reports_count);
        $this->assertCount(3, $queue->first()->reports);
        $this->assertNull($queue->first()->reports->first()->reporter_user_id, 'The reporter is not even loaded.');
    }

    public function test_hidden_reviews_can_be_listed_for_reversal(): void
    {
        $shop = Shop::factory()->create();
        $hidden = Review::factory()->for($shop)->hidden()->create();
        Review::factory()->for($shop)->create();
        Review::factory()->hidden()->create();

        $this->assertSame([$hidden->getKey()], $this->moderation()->hiddenReviews($this->moderatorOf($shop), $shop->mall)->pluck('review_id')->all());
    }
}
