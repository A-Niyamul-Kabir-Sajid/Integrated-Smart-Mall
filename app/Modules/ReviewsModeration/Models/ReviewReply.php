<?php

namespace App\Modules\ReviewsModeration\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ReplyStatus;
use Database\Factories\ReviewReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * REVIEW_REPLY: a public answer to a review by authorised staff of the reviewed shop.
 */
#[Table(name: 'review_replies', key: 'review_reply_id')]
#[Fillable(['review_id', 'author_user_id', 'body', 'status'])]
#[UseFactory(ReviewReplyFactory::class)]
class ReviewReply extends Model
{
    /** @use HasFactory<ReviewReplyFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (ReviewReply $reply): void {
            if ($reply->isDirty('review_id')) {
                throw new LogicException('A reply never moves to another review.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Replies are hidden, never deleted.');
        });
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'review_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', ReplyStatus::Published->value);
    }

    protected function casts(): array
    {
        return [
            'status' => ReplyStatus::class,
        ];
    }
}
