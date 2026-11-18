<?php

namespace App\Modules\ChatbotRag\Models;

use App\Modules\ChatbotRag\Enums\MessageRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CHAT_MESSAGE: one question or one answer, in order. An answer keeps in meta how it was produced
 * (tool, outcome, driver, live facts shown, destinations offered) so the conversation can be shown
 * again; the chunks it cited and the listings it recommended are separate rows.
 */
#[Table(name: 'chat_messages', key: 'message_id', timestamps: false)]
#[Fillable(['conversation_id', 'sequence_no', 'role', 'content', 'model_label', 'meta', 'created_at'])]
class ChatMessage extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * @return HasMany<MessageCitation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(MessageCitation::class, 'message_id')->orderBy('citation_no');
    }

    /**
     * @return HasMany<MessageRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(MessageRecommendation::class, 'message_id')->orderBy('rank_no');
    }

    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'role' => MessageRole::class,
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
