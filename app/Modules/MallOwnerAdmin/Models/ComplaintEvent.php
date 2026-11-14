<?php

namespace App\Modules\MallOwnerAdmin\Models;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry of a complaint's history. Entries are append-only.
 */
#[Table(name: 'complaint_events', key: 'complaint_event_id', timestamps: false)]
#[Fillable(['complaint_id', 'from_status', 'to_status', 'actor_user_id', 'note', 'created_at'])]
class ComplaintEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Complaint history is final and cannot be changed.'));
        static::deleting(fn (): never => throw new LogicException('Complaint history is final and cannot be deleted.'));
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
        return ['created_at' => 'immutable_datetime'];
    }
}
