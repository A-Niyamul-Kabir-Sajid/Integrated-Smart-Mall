<?php

namespace App\Modules\ReviewsModeration\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Read side of reviews: the public list for a shop or a listing, a customer's own reviews,
 * and the shop's review inbox. Public lists only ever contain published reviews and the
 * published replies to them, and never expose reporter or moderator details.
 */
class ReviewQueryService
{
    public const PER_PAGE = 10;

    /**
     * Published reviews of the shop itself (not of its products), newest first.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function publicForShop(Shop $shop, bool $verifiedOnly = false, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->publicList(Review::query()->where('shop_id', $shop->getKey())->whereNull('shop_product_id'), $verifiedOnly, $perPage);
    }

    /**
     * Published reviews of one shop listing, newest first.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function publicForListing(ShopProduct $listing, bool $verifiedOnly = false, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->publicList(Review::query()->where('shop_product_id', $listing->getKey()), $verifiedOnly, $perPage);
    }

    /**
     * The customer's own review of a target in any status, or null.
     */
    public function ownReview(User $author, Shop $shop, ?ShopProduct $listing = null): ?Review
    {
        return Review::query()
            ->where('author_user_id', $author->getKey())
            ->where('shop_id', $shop->getKey())
            ->where('shop_product_id', $listing?->getKey())
            ->with(['verification', 'replies' => fn ($replies) => $replies->published()])
            ->first();
    }

    /**
     * Every review the customer wrote, in any status, newest first.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function forAuthor(User $author, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return Review::query()
            ->where('author_user_id', $author->getKey())
            ->with(['shop:shop_id,mall_id,slug,name', 'listing.variant.product', 'verification', 'replies' => fn ($replies) => $replies->published()])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * The shop's published reviews (of the shop and of its products) with their replies, for staff
     * of that shop only. Optionally only the ones nobody answered yet.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function inboxForShop(User $staff, Shop $shop, bool $unansweredOnly = false, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        Gate::forUser($staff)->authorize(Ability::WorkInShop->value, $shop);

        return Review::query()
            ->published()
            ->where('shop_id', $shop->getKey())
            ->when($unansweredOnly, fn (Builder $reviews) => $reviews->whereDoesntHave('replies', fn ($replies) => $replies->published()))
            ->with(['author:user_id,display_name', 'listing.variant.product', 'verification', 'replies' => fn ($replies) => $replies->published()])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<Review>  $base
     * @return LengthAwarePaginator<int, Review>
     */
    private function publicList(Builder $base, bool $verifiedOnly, int $perPage): LengthAwarePaginator
    {
        return $base
            ->published()
            ->when($verifiedOnly, fn (Builder $reviews) => $reviews->verified())
            ->with(['author:user_id,display_name', 'verification', 'replies' => fn ($replies) => $replies->published()])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
