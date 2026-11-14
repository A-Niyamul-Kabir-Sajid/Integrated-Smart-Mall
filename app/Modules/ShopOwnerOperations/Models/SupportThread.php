<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\SupportKind;
use App\Modules\ShopOwnerOperations\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A customer's conversation with one shop (decision D-014). The subject, kind, shop and
 * customer are fixed when it starts; the status and the last-message time change.
 */
#[Table(name: 'support_threads', key: 'support_thread_id')]
#[Fillable(['shop_id', 'customer_user_id', 'subject', 'kind', 'status', 'last_message_at', 'closed_at'])]
class SupportThread extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (SupportThread $thread): void {
            foreach (['shop_id', 'customer_user_id', 'subject', 'kind'] as $attribute) {
                if ($thread->isDirty($attribute)) {
                    throw new LogicException("Support thread {$attribute} cannot change.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Support threads are never deleted; close them instead.');
        });
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /**
     * @return HasMany<SupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_thread_id')->orderBy('created_at')->orderBy('support_message_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => SupportKind::class,
            'status' => SupportStatus::class,
            'last_message_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
