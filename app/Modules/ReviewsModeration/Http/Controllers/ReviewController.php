<?php

namespace App\Modules\ReviewsModeration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\Enums\ReportReason;
use App\Modules\ReviewsModeration\Http\Requests\SubmitReviewRequest;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Services\ReviewPolicy;
use App\Modules\ReviewsModeration\Services\ReviewQueryService;
use App\Modules\ReviewsModeration\Services\ReviewService;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Customer review pages: read the published reviews of a shop or a product listing,
 * write, edit, withdraw and verify your own.
 */
class ReviewController extends Controller
{
    public function shop(Request $request, Mall $publicMall, Shop $publicShop, ReviewQueryService $reviews, ReviewSummaryLookup $summaries): View
    {
        return $this->page($request, $publicMall, $publicShop, null, $reviews, $summaries);
    }

    public function listing(Request $request, Mall $publicMall, ShopProduct $publicListing, ReviewQueryService $reviews, ReviewSummaryLookup $summaries): View
    {
        return $this->page($request, $publicMall, $publicListing->shop, $publicListing, $reviews, $summaries);
    }

    public function storeForShop(SubmitReviewRequest $request, Mall $publicMall, Shop $publicShop, ReviewService $reviews): RedirectResponse
    {
        $reviews->submitShopReview($request->user(), $publicShop, $request->reviewData());

        return redirect()->route('shops.reviews.index', ['publicMall' => $publicMall->slug, 'publicShop' => $publicShop->slug])
            ->with('status', __('Thank you, your review is published.'));
    }

    public function storeForListing(SubmitReviewRequest $request, Mall $publicMall, ShopProduct $publicListing, ReviewService $reviews): RedirectResponse
    {
        $reviews->submitProductReview($request->user(), $publicListing, $request->reviewData(), $request->validated('purchase_code'));

        return redirect()->route('listings.reviews.index', ['publicMall' => $publicMall->slug, 'publicListing' => $publicListing->getKey()])
            ->with('status', __('Thank you, your review is published.'));
    }

    public function withdraw(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        $reviews->withdrawReview($request->user(), $review);

        return redirect()->route('account.reviews')->with('status', __('Your review was withdrawn.'));
    }

    public function verify(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        $code = $request->validate(['purchase_code' => ['required', 'string', 'max:100']])['purchase_code'];
        $reviews->verifyOwnReview($request->user(), $review, $code);

        return back()->with('status', __('Your review is now marked as a verified purchase.'));
    }

    public function mine(Request $request, ReviewQueryService $reviews): View
    {
        return view('customer.reviews.mine', ['reviews' => $reviews->forAuthor($request->user())]);
    }

    private function page(Request $request, Mall $mall, Shop $shop, ?ShopProduct $listing, ReviewQueryService $reviews, ReviewSummaryLookup $summaries): View
    {
        $verifiedOnly = $listing !== null && $request->boolean('verified');
        $user = $request->user();
        $own = $user === null ? null : $reviews->ownReview($user, $shop, $listing);

        return view('customer.reviews.index', [
            'mall' => $mall,
            'shop' => $shop,
            'listing' => $listing?->loadMissing('variant.product'),
            'summary' => $listing === null ? $summaries->forShop($shop->getKey()) : $summaries->forListing($listing->getKey()),
            'reviews' => $listing === null ? $reviews->publicForShop($shop) : $reviews->publicForListing($listing, $verifiedOnly),
            'verifiedOnly' => $verifiedOnly,
            'own' => $own,
            'ownCanEdit' => $own !== null && ReviewPolicy::authorMayEdit($own),
            'isStaff' => $user !== null && Gate::forUser($user)->allows(Ability::WorkInShop->value, $shop),
            'reasons' => ReportReason::cases(),
        ]);
    }
}
