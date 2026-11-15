<?php

namespace App\Modules\ChatbotRag\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MESSAGE_CITATION: a chunk an answer rests on, with the excerpt it quoted. The table has a
 * composite primary key (message_id, chunk_id), so rows are read through the message relationship
 * and written with query-builder inserts.
 */
#[Table(name: 'message_citations', incrementing: false, timestamps: false)]
#[Fillable(['message_id', 'chunk_id', 'citation_no', 'retrieval_score', 'quoted_excerpt'])]
class MessageCitation extends Model
{
    protected $primaryKey = null;

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * @return BelongsTo<KnowledgeChunk, $this>
     */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'chunk_id');
    }

    protected function casts(): array
    {
        return [
            'citation_no' => 'integer',
            'retrieval_score' => 'float',
        ];
    }
}
