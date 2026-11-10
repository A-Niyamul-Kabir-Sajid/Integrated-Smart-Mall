<?php

namespace App\Modules\ReviewsModeration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use App\Modules\ReviewsModeration\Services\ModerationService;
use App\Modules\ReviewsModeration\Services\ReviewPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The review moderation desk of one mall. Routes require administer-mall for the mall in the
 * URL; a review or report of another mall is not found even through this mall's URL.
 */
class ModerationController extends Controller
{
    public function index(Request $request, Mall $mall, ModerationService $moderation): View
    {
        $view = $request->validate(['view' => ['nullable', Rule::in(['reported', 'hidden'])]])['view'] ?? 'reported';

        return view('mall-admin.moderation.index', [
            'mall' => $mall,
            'view' => $view,
            'reviews' => $view === 'hidden'
                ? $moderation->hiddenReviews($request->user(), $mall)
                : $moderation->reportedReviews($request->user(), $mall),
        ]);
    }

    public function hide(Request $request, Mall $mall, Review $review, ModerationService $moderation): RedirectResponse
    {
        $this->ensureInMall($mall, $review);
        $moderation->hide($request->user(), $review, $this->reason($request));

        return back()->with('status', __('The review is hidden and its reports are resolved.'));
    }

    public function restore(Request $request, Mall $mall, Review $review, ModerationService $moderation): RedirectResponse
    {
        $this->ensureInMall($mall, $review);
        $moderation->restore($request->user(), $review, $this->reason($request));

        return back()->with('status', __('The review is visible again.'));
    }

    public function publish(Request $request, Mall $mall, Review $review, ModerationService $moderation): RedirectResponse
    {
        $this->ensureInMall($mall, $review);
        $moderation->publish($request->user(), $review, $this->reason($request));

        return back()->with('status', __('The review is published.'));
    }

    public function dismiss(Request $request, Mall $mall, Review $review, ModerationService $moderation): RedirectResponse
    {
        $this->ensureInMall($mall, $review);
        $moderation->dismissOpenReports($request->user(), $review, $this->reason($request));

        return back()->with('status', __('The reports were dismissed and the review stays.'));
    }

    public function dismissReport(Request $request, Mall $mall, ReviewReport $report, ModerationService $moderation): RedirectResponse
    {
        abort_unless($report->review()->whereHas('shop', fn ($shops) => $shops->where('mall_id', $mall->getKey()))->exists(), 404);
        $moderation->dismissReport($request->user(), $report, $this->reason($request));

        return back()->with('status', __('The report was dismissed.'));
    }

    public function revokeVerification(Request $request, Mall $mall, Review $review, ModerationService $moderation): RedirectResponse
    {
        $this->ensureInMall($mall, $review);
        $moderation->revokeVerification($request->user(), $review, $this->reason($request));

        return back()->with('status', __('The verified-purchase badge was removed.'));
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'max:'.ReviewPolicy::REASON_MAX]])['reason'];
    }

    private function ensureInMall(Mall $mall, Review $review): void
    {
        abort_unless($review->shop()->where('mall_id', $mall->getKey())->exists(), 404);
    }
}
