<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One message of a support thread. Messages are append-only.
 */
#[Table(name: 'support_messages', key: 'support_message_id', timestamps: false)]
#[Fillable(['support_thread_id', 'author_user_id', 'author_side', 'body', 'created_at'])]
class SupportMessage extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A support message cannot be edited.');
        });

        static::deleting(function (): never {
            throw new LogicException('A support message cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
