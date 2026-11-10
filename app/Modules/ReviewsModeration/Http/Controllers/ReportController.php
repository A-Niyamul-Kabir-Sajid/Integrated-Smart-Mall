<?php

namespace App\Modules\ReviewsModeration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ReviewsModeration\Enums\ReportReason;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Services\ReviewPolicy;
use App\Modules\ReviewsModeration\Services\ReviewReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lets a signed-in customer report a review. Never answers with anything about other reports.
 */
class ReportController extends Controller
{
    public function store(Request $request, Review $review, ReviewReportService $reports): RedirectResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:'.ReviewPolicy::REPORT_DETAILS_MAX],
        ]);

        $reports->report($request->user(), $review, ReportReason::from($data['reason_code']), $data['details'] ?? null);

        return back()->with('status', __('Thank you. A moderator will look at this review.'));
    }
}
