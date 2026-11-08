<?php

namespace App\Modules\ReviewsModeration\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ReviewsModeration\Enums\ModerationActionType;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use Database\Factories\ModerationActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * MODERATION_ACTION: one append-only entry in a review's moderation history.
 */
#[Table(name: 'moderation_actions', key: 'moderation_action_id')]
#[Fillable(['review_id', 'review_report_id', 'actor_user_id', 'action', 'reason', 'previous_status', 'resulting_status'])]
#[UseFactory(ModerationActionFactory::class)]
class ModerationAction extends Model
{
    /** @use HasFactory<ModerationActionFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Moderation history is append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Moderation history is append-only.');
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
     * @return BelongsTo<ReviewReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(ReviewReport::class, 'review_report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'action' => ModerationActionType::class,
            'previous_status' => ReviewStatus::class,
            'resulting_status' => ReviewStatus::class,
        ];
    }
}
