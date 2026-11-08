<?php

namespace App\Modules\ReviewsModeration\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Contracts\PurchaseVerifier;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewVerification;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SensitiveParameter;

/**
 * Writes a customer's own reviews: one persistent review per shop or listing, edited
 * in place within the edit window, withdrawn rather than deleted.
 *
 * The verified badge is never an input. A product review is verified only by presenting a
 * purchase code, which is redeemed through PurchaseVerification inside the same transaction
 * that saves the review: if the code is refused, nothing is saved.
 */
class ReviewService
{
    public function __construct(
        private DirectoryLookup $directory,
        private PurchaseVerifier $verifier,
        private AuditLogger $audit,
    ) {}

    /**
     * Writes or updates the author's review of a shop. Only rating, title and body are read.
     *
     * @param  array{rating: int|string, title?: string|null, body: string}  $data
     */
    public function submitShopReview(User $author, Shop $shop, array $data): Review
    {
        return $this->save($author, $shop, null, $data, null);
    }

    /**
     * Writes or updates the author's review of one shop listing, optionally verifying it with a
     * purchase code from the shop.
     *
     * @param  array{rating: int|string, title?: string|null, body: string}  $data
     */
    public function submitProductReview(User $author, ShopProduct $listing, array $data, #[SensitiveParameter] ?string $purchaseCode = null): Review
    {
        return $this->save($author, $listing->shop, $listing, $data, $purchaseCode);
    }

    /**
     * Verifies one of the author's existing, unverified product reviews with a purchase code.
     */
    public function verifyOwnReview(User $author, Review $review, #[SensitiveParameter] string $purchaseCode): Review
    {
        $this->ensureAuthor($author, $review);

        if ($review->isShopReview()) {
            throw new BusinessRuleViolation('review.verification_needs_product', __('Only reviews of a product can be verified with a purchase code.'));
        }

        if (! in_array($review->status, [ReviewStatus::Published, ReviewStatus::Pending], true)) {
            throw new BusinessRuleViolation('review.not_active', __('Only a visible review can be verified.'));
        }

        DB::transaction(function () use ($author, $review, $purchaseCode): void {
            $this->bindVerification($author, $review, $purchaseCode);
            $this->audit->record('review.verified', $review, ['rating' => $review->rating], $review->shop->mall_id, $review->shop_id);
        });

        return $review->load('verification');
    }

    /**
     * Withdraws the author's review. The row and any purchase binding are kept; a later
     * submission for the same target brings the review back.
     */
    public function withdrawReview(User $author, Review $review): Review
    {
        $this->ensureAuthor($author, $review);

        if ($review->status === ReviewStatus::Hidden) {
            throw new BusinessRuleViolation('review.hidden_by_moderation', __('This review was hidden by a moderator and cannot be changed.'));
        }

        if ($review->status === ReviewStatus::Withdrawn) {
            throw new BusinessRuleViolation('review.already_withdrawn', __('This review is already withdrawn.'));
        }

        $review->update(['status' => ReviewStatus::Withdrawn]);
        $this->audit->record('review.withdrawn', $review, [], $review->shop->mall_id, $review->shop_id);

        return $review;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(User $author, Shop $shop, ?ShopProduct $listing, array $data, ?string $purchaseCode): Review
    {
        $content = $this->content($data);
        $purchaseCode = $purchaseCode === null || trim($purchaseCode) === '' ? null : $purchaseCode;

        if (! $this->directory->isShopOperational($shop->getKey())) {
            throw new BusinessRuleViolation('review.shop_not_reviewable', __('Only an active shop can be reviewed.'));
        }

        if ($listing !== null && $listing->status !== 'active') {
            throw new BusinessRuleViolation('review.listing_not_reviewable', __('This product is no longer offered, so it cannot be reviewed.'));
        }

        if (Gate::forUser($author)->allows(Ability::WorkInShop->value, $shop)) {
            throw new BusinessRuleViolation('review.own_shop', __('You work at this shop, so you cannot review it or its products.'));
        }

        try {
            $review = DB::transaction(function () use ($author, $shop, $listing, $content, $purchaseCode): Review {
                $existing = Review::query()
                    ->where('author_user_id', $author->getKey())
                    ->where('shop_id', $shop->getKey())
                    ->where('shop_product_id', $listing?->getKey())
                    ->lockForUpdate()
                    ->first();

                $review = $existing === null
                    ? $this->create($author, $shop, $listing, $content)
                    : $this->update($existing, $content);

                if ($purchaseCode !== null) {
                    $this->bindVerification($author, $review, $purchaseCode);
                }

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('review.duplicate', __('You already reviewed this. Open your review to change it.'));
        }

        return $review->load('verification');
    }

    /**
     * @param  array{rating: int, title: string|null, body: string}  $content
     */
    private function create(User $author, Shop $shop, ?ShopProduct $listing, array $content): Review
    {
        $review = Review::query()->create([
            'author_user_id' => $author->getKey(),
            'shop_id' => $shop->getKey(),
            'shop_product_id' => $listing?->getKey(),
            ...$content,
            'status' => ReviewStatus::Published,
        ]);

        $this->audit->record('review.submitted', $review, ['rating' => $review->rating, 'target' => $listing === null ? 'shop' : 'listing'], $shop->mall_id, $shop->getKey());

        return $review;
    }

    /**
     * @param  array{rating: int, title: string|null, body: string}  $content
     */
    private function update(Review $review, array $content): Review
    {
        if ($review->status === ReviewStatus::Hidden) {
            throw new BusinessRuleViolation('review.hidden_by_moderation', __('This review was hidden by a moderator and cannot be changed.'));
        }

        if (! ReviewPolicy::authorMayEdit($review)) {
            throw new BusinessRuleViolation('review.edit_window_closed', __('Reviews can be edited for :days days after they are written. You can still withdraw this one.', ['days' => ReviewPolicy::EDIT_WINDOW_DAYS]));
        }

        $review->fill($content);

        if ($review->status === ReviewStatus::Withdrawn) {
            $review->status = ReviewStatus::Published;
        }

        $review->save();
        $this->audit->record('review.updated', $review, ['rating' => $review->rating], $review->shop->mall_id, $review->shop_id);

        return $review;
    }

    private function bindVerification(User $author, Review $review, #[SensitiveParameter] string $purchaseCode): void
    {
        if (ReviewVerification::query()->whereKey($review->getKey())->exists()) {
            throw new BusinessRuleViolation('review.already_verified', __('This review is already verified.'));
        }

        $result = $this->verifier->redeem($author->getKey(), $purchaseCode, $review->shop_product_id);

        ReviewVerification::query()->create([
            'review_id' => $review->getKey(),
            'verification_token_id' => $result->verificationTokenId,
            'verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{rating: int, title: string|null, body: string}
     */
    private function content(array $data): array
    {
        $rating = $data['rating'] ?? null;

        if (! is_numeric($rating) || (int) $rating != $rating || (int) $rating < 1 || (int) $rating > 5) {
            throw new BusinessRuleViolation('review.invalid_rating', __('Choose a rating from 1 to 5.'));
        }

        $title = trim((string) ($data['title'] ?? ''));

        if (mb_strlen($title) > ReviewPolicy::TITLE_MAX) {
            throw new BusinessRuleViolation('review.invalid_title', __('The title can have at most :max characters.', ['max' => ReviewPolicy::TITLE_MAX]));
        }

        $body = trim((string) ($data['body'] ?? ''));

        if (mb_strlen($body) < ReviewPolicy::BODY_MIN || mb_strlen($body) > ReviewPolicy::BODY_MAX) {
            throw new BusinessRuleViolation('review.invalid_body', __('Write between :min and :max characters.', ['min' => ReviewPolicy::BODY_MIN, 'max' => ReviewPolicy::BODY_MAX]));
        }

        return ['rating' => (int) $rating, 'title' => $title === '' ? null : $title, 'body' => $body];
    }

    private function ensureAuthor(User $author, Review $review): void
    {
        if ($review->author_user_id !== $author->getKey()) {
            throw new AuthorizationException('Only the author may change a review.');
        }
    }
}
