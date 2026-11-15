<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one place that decides which chunks a reader may retrieve. Every search and every load of a
 * chunk for an answer starts from this query, so the rule cannot be forgotten in one of them:
 * the document belongs to the mall, is active, is in a scope the reader may see, and the chunk is
 * part of the document's current revision.
 */
class RetrievableChunks
{
    /**
     * @param  list<string>  $scopes  Access scopes the reader may see; an empty list retrieves nothing
     * @param  list<string>|null  $kinds
     */
    public function query(string $mallId, array $scopes, ?array $kinds = null): Builder
    {
        return DB::table('knowledge_chunks')
            ->join('document_revisions', 'document_revisions.revision_id', '=', 'knowledge_chunks.revision_id')
            ->join('knowledge_documents', function ($join): void {
                $join->on('knowledge_documents.document_id', '=', 'document_revisions.document_id')
                    ->on('knowledge_documents.current_revision_id', '=', 'document_revisions.revision_id');
            })
            ->where('knowledge_documents.mall_id', $mallId)
            ->where('knowledge_documents.status', 'active')
            ->whereIn('knowledge_documents.access_scope', $scopes)
            ->when($kinds !== null, fn (Builder $query) => $query->whereIn('knowledge_documents.source_kind', $kinds));
    }
}
