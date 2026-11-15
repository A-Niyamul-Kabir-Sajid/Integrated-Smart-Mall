<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

use App\Modules\ChatbotRag\Contracts\ChunkSearch;
use Illuminate\Database\Query\Builder;

/**
 * Retrieval on PostgreSQL (decision D-018): full-text search over the chunk's tsvector, ranked with
 * ts_rank_cd, plus pg_trgm word similarity so that a misspelt word still finds its chunk. The words
 * are OR-ed: a chunk matching more of them ranks higher, and the caller measures how much of the
 * question it covers.
 */
class PostgresChunkSearch implements ChunkSearch
{
    /**
     * Words shorter than this are too short for a trigram comparison to mean anything.
     */
    private const FUZZY_MIN_LENGTH = 4;

    private const FUZZY_MAX_WORDS = 8;

    private const TS_QUERY = "(to_tsquery('english'::regconfig, ?) || to_tsquery('simple'::regconfig, ?))";

    public function __construct(private RetrievableChunks $chunks) {}

    public function search(string $mallId, array $scopes, array $words, ?array $kinds, int $limit): array
    {
        $words = array_values(array_filter($words, fn (string $word): bool => preg_match('/^[\p{L}\p{M}\p{N}]+$/u', $word) === 1));

        if ($words === [] || $scopes === []) {
            return [];
        }

        $lexemes = implode(' | ', $words);
        $fuzzy = array_slice(array_values(array_filter($words, fn (string $word): bool => mb_strlen($word) >= self::FUZZY_MIN_LENGTH)), 0, self::FUZZY_MAX_WORDS);
        $similarity = $fuzzy === []
            ? '0'
            : 'greatest('.implode(', ', array_fill(0, count($fuzzy), 'word_similarity(?, knowledge_chunks.search_text)')).', 0)';

        $rows = $this->chunks->query($mallId, $scopes, $kinds)
            ->selectRaw('knowledge_chunks.chunk_id, ts_rank_cd(knowledge_chunks.search_vector, '.self::TS_QUERY.", 32) as rank, {$similarity} as similarity", [$lexemes, $lexemes, ...$fuzzy])
            ->where(function (Builder $match) use ($lexemes, $fuzzy): void {
                $match->whereRaw('knowledge_chunks.search_vector @@ '.self::TS_QUERY, [$lexemes, $lexemes]);

                foreach ($fuzzy as $word) {
                    $match->orWhereRaw('? <% knowledge_chunks.search_text', [$word]);
                }
            })
            ->orderByDesc('rank')
            ->orderByDesc('similarity')
            ->orderBy('knowledge_chunks.chunk_id')
            ->limit($limit)
            ->get();

        $found = [];

        foreach ($rows as $row) {
            $found[(string) $row->chunk_id] = (float) $row->rank + 0.3 * (float) $row->similarity;
        }

        arsort($found);

        return $found;
    }
}
