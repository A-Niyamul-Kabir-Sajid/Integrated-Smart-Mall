<?php

namespace App\Modules\ReviewsModeration\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ReplyStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReply;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;

/**
 * Shop answers to reviews. Only staff of the reviewed shop (work-in-shop for that shop)
 * may write, edit or hide a reply, only on a public review, and a reply never touches the
 * customer's own words.
 */
class ReviewReplyService
{
    public function __construct(private AuditLogger $audit) {}

    public function reply(User $staff, Review $review, string $body): ReviewReply
    {
        Gate::forUser($staff)->authorize(Ability::WorkInShop->value, $review->shop);

        if (! $review->status->isPublic()) {
            throw new BusinessRuleViolation('reply.review_not_public', __('You can only reply to a published review.'));
        }

        if ($review->replies()->published()->exists()) {
            throw new BusinessRuleViolation('reply.already_exists', __('This review already has a reply; edit it instead.'));
        }

        $reply = $review->replies()->create([
            'author_user_id' => $staff->getKey(),
            'body' => $this->body($body),
            'status' => ReplyStatus::Published,
        ]);

        $this->audit->record('review.reply_posted', $reply, ['review_id' => $review->getKey()], $review->shop->mall_id, $review->shop_id);

        return $reply;
    }

    public function editReply(User $staff, ReviewReply $reply, string $body): ReviewReply
    {
        $review = $reply->review;
        Gate::forUser($staff)->authorize(Ability::WorkInShop->value, $review->shop);

        if ($reply->status !== ReplyStatus::Published) {
            throw new BusinessRuleViolation('reply.not_public', __('This reply is hidden and cannot be edited.'));
        }

        $reply->update(['body' => $this->body($body)]);
        $this->audit->record('review.reply_edited', $reply, ['review_id' => $review->getKey()], $review->shop->mall_id, $review->shop_id);

        return $reply;
    }

    public function hideReply(User $staff, ReviewReply $reply): ReviewReply
    {
        $review = $reply->review;
        Gate::forUser($staff)->authorize(Ability::WorkInShop->value, $review->shop);

        if ($reply->status === ReplyStatus::Published) {
            $reply->update(['status' => ReplyStatus::Hidden]);
            $this->audit->record('review.reply_hidden', $reply, ['review_id' => $review->getKey()], $review->shop->mall_id, $review->shop_id);
        }

        return $reply;
    }

    private function body(string $body): string
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > ReviewPolicy::REPLY_MAX) {
            throw new BusinessRuleViolation('reply.invalid_body', __('Write a reply of up to :max characters.', ['max' => ReviewPolicy::REPLY_MAX]));
        }

        return $body;
    }
}
