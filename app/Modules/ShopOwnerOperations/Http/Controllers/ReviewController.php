<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReply;
use App\Modules\ReviewsModeration\Services\ReviewPolicy;
use App\Modules\ReviewsModeration\Services\ReviewQueryService;
use App\Modules\ReviewsModeration\Services\ReviewReplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The shop's review inbox in the shop workspace. Routes require work-in-shop for the
 * shop in the URL; reviews and replies of any other shop are not found, and all writes
 * go through ReviewsModeration's ReviewReplyService.
 */
class ReviewController extends Controller
{
    public function index(Request $request, Shop $shop, ReviewQueryService $reviews): View
    {
        $unanswered = $request->boolean('unanswered');

        return view('shop-owner.reviews.index', [
            'shop' => $shop,
            'unanswered' => $unanswered,
            'reviews' => $reviews->inboxForShop($request->user(), $shop, $unanswered),
        ]);
    }

    public function reply(Request $request, Shop $shop, Review $review, ReviewReplyService $replies): RedirectResponse
    {
        abort_unless($review->shop_id === $shop->getKey(), 404);

        $replies->reply($request->user(), $review, $this->body($request));

        return back()->with('status', __('Your reply was published.'));
    }

    public function updateReply(Request $request, Shop $shop, ReviewReply $reply, ReviewReplyService $replies): RedirectResponse
    {
        $this->ensureReplyBelongsToShop($shop, $reply);

        $replies->editReply($request->user(), $reply, $this->body($request));

        return back()->with('status', __('Your reply was updated.'));
    }

    public function hideReply(Request $request, Shop $shop, ReviewReply $reply, ReviewReplyService $replies): RedirectResponse
    {
        $this->ensureReplyBelongsToShop($shop, $reply);

        $replies->hideReply($request->user(), $reply);

        return back()->with('status', __('Your reply was removed from the review.'));
    }

    private function body(Request $request): string
    {
        return $request->validate(['body' => ['required', 'string', 'max:'.ReviewPolicy::REPLY_MAX]])['body'];
    }

    private function ensureReplyBelongsToShop(Shop $shop, ReviewReply $reply): void
    {
        abort_unless($reply->review()->where('shop_id', $shop->getKey())->exists(), 404);
    }
}
