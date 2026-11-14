<?php

namespace App\Modules\MallOwnerAdmin\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallOwnerAdmin\Enums\AnnouncementKind;
use App\Modules\MallOwnerAdmin\Enums\AnnouncementStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A mall-wide notice or event written by the mall's administrators (decision D-015). It never
 * moves to another mall and is never deleted; archive it to take it down.
 */
#[Table(name: 'announcements', key: 'announcement_id')]
#[Fillable(['mall_id', 'kind', 'title', 'body', 'location_note', 'status', 'starts_at', 'ends_at', 'published_at', 'created_by_user_id'])]
class Announcement extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (Announcement $announcement): void {
            foreach (['mall_id', 'created_by_user_id'] as $attribute) {
                if ($announcement->isDirty($attribute)) {
                    throw new LogicException("Announcement {$attribute} cannot change.");
                }
            }

            if ($announcement->isDirty('status')) {
                $from = AnnouncementStatus::from($announcement->getRawOriginal('status'));

                if (! in_array($announcement->status, $from->allowedTransitions(), true)) {
                    throw new LogicException("A {$from->value} announcement cannot become {$announcement->status->value}.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Announcements are never deleted; archive them instead.');
        });
    }

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Published and not yet over: what customers may see now. A notice waits for its start time;
     * an event is announced from publication so people can plan for it.
     */
    #[Scope]
    protected function visibleNow(Builder $query): Builder
    {
        return $query->where('status', AnnouncementStatus::Published->value)
            ->where(fn (Builder $window) => $window->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn (Builder $window) => $window->where('kind', 'event')->orWhereNull('starts_at')->orWhere('starts_at', '<=', now()));
    }

    protected function casts(): array
    {
        return [
            'kind' => AnnouncementKind::class,
            'status' => AnnouncementStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
