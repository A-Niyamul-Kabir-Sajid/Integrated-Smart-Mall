<?php

namespace App\Modules\ChatbotRag\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KNOWLEDGE_CHUNK: a retrieval-sized piece of one revision. chunk_text is what an answer quotes;
 * search_text (document title, section heading and text, lower case) is what retrieval matches.
 */
#[Table(name: 'knowledge_chunks', key: 'chunk_id', timestamps: false)]
#[Fillable(['revision_id', 'chunk_no', 'heading', 'chunk_text', 'search_text', 'token_count'])]
class KnowledgeChunk extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<DocumentRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'revision_id');
    }

    protected function casts(): array
    {
        return [
            'chunk_no' => 'integer',
            'token_count' => 'integer',
        ];
    }
}
