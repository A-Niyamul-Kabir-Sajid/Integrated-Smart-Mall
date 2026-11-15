<?php

namespace App\Modules\ChatbotRag\Contracts;

/**
 * Finds the chunks that may answer a question. The implementation depends on the database:
 * PostgreSQL full-text search with trigram matching in production, a portable scan on SQLite.
 *
 * Every implementation searches only current revisions of active documents of the given mall in the
 * given access scopes; the caller cannot widen that.
 */
interface ChunkSearch
{
    /**
     * @param  list<string>  $scopes  Access scopes the reader may see
     * @param  list<string>  $words  Lower-case words of the question and their synonyms, letters and digits only
     * @param  list<string>|null  $kinds  Source kinds to search, or null for all
     * @return array<string, float> Chunk id => relevance according to the database, best first
     */
    public function search(string $mallId, array $scopes, array $words, ?array $kinds, int $limit): array;
}
