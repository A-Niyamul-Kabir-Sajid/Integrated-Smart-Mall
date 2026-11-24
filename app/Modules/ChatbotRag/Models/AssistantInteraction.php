<?php

namespace App\Modules\ChatbotRag\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One question to the assistant and how it ended, for the administrators' activity page. It holds
 * no account, no session and no conversation, so it cannot be traced to a person. The question
 * text is kept only when a visitor's question could not be answered well, with personal details
 * already removed, so that the mall can see what its documents are missing.
 */
#[Table(name: 'assistant_interactions', key: 'interaction_id', timestamps: false)]
#[Fillable(['mall_id', 'access_scope', 'tool', 'outcome', 'language', 'driver', 'fallback_reason', 'refusal_reason', 'question', 'created_at'])]
class AssistantInteraction extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
