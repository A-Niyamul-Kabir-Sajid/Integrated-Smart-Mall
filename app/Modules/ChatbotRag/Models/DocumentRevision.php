<?php

namespace App\Modules\ChatbotRag\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * DOCUMENT_REVISION: an immutable snapshot of a document's text, so that an answer's citation can be
 * reproduced after the document changed. A change to the text is always a new revision.
 */
#[Table(name: 'document_revisions', key: 'revision_id', timestamps: false)]
#[Fillable(['document_id', 'revision_no', 'content_hash', 'content_text', 'source_updated_at', 'captured_at'])]
class DocumentRevision extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('A document revision is immutable; capture a new revision instead.'));
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'revision_id')->orderBy('chunk_no');
    }

    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'source_updated_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
        ];
    }
}
