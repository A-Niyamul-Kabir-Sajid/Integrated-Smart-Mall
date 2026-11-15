<?php

namespace App\Modules\ChatbotRag\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\MallShopDirectory\Models\Mall;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CONVERSATION: one visitor's or administrator's exchange with the assistant in one mall. A guest's
 * conversation has no user and is reachable only through the session that started it; knowing its
 * id opens nothing.
 */
#[Table(name: 'conversations', key: 'conversation_id', timestamps: false)]
#[Fillable(['mall_id', 'user_id', 'access_scope', 'created_at', 'expires_at'])]
#[UseFactory(ConversationFactory::class)]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUuids;

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('sequence_no');
    }

    #[Scope]
    protected function unexpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    protected function casts(): array
    {
        return [
            'access_scope' => AccessScope::class,
            'created_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
