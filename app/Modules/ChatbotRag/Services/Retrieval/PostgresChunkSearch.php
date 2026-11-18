<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

use App\Modules\ChatbotRag\Contracts\ChunkSearch;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrieval on PostgreSQL (decision D-018): full-text search over the chunk's tsvector plus pg_trgm
 * word similarity, so that a misspelt word still finds its chunk. A chunk is a candidate when it
 * holds any word of the question (or a synonym) or is trigram-similar to a typed word; candidates
 * are ordered by how many of the question's words they hold, then by ts_rank_cd, then by similarity.
 */
class PostgresChunkSearch implements ChunkSearch
{
    /**
     * Words shorter than this are too short for a trigram comparison to mean anything.
     */
    private const FUZZY_MIN_LENGTH = 4;

    /**
     * Lowest pg_trgm word similarity for a typed word to make a chunk a candidate. Candidates are
     * still scored by coverage afterwards, so a loose match here never becomes an answer on its own.
     */
    private const FUZZY_THRESHOLD = 0.45;

    private const MAX_GROUPS = 12;

    private const TS_QUERY = "(to_tsquery('english'::regconfig, ?) || to_tsquery('simple'::regconfig, ?))";

    public function __construct(private RetrievableChunks $chunks) {}

    public function search(string $mallId, array $scopes, array $wordGroups, ?array $kinds, int $limit): array
    {
        $groups = [];

        foreach (array_slice($wordGroups, 0, self::MAX_GROUPS) as $group) {
            $words = array_values(array_filter($group, fn (string $word): bool => preg_match('/^[\p{L}\p{M}\p{N}]+$/u', $word) === 1));

            if ($words !== []) {
                $groups[] = $words;
            }
        }

        if ($groups === [] || $scopes === []) {
            return [];
        }

        $any = implode(' | ', array_merge(...$groups));
        $typed = array_values(array_filter(array_map(fn (array $group): string => $group[0], $groups), fn (string $word): bool => mb_strlen($word) >= self::FUZZY_MIN_LENGTH));

        $matched = implode(' + ', array_fill(0, count($groups), '(knowledge_chunks.search_vector @@ '.self::TS_QUERY.')::int'));
        $matchedBindings = array_merge(...array_map(fn (array $group): array => [implode(' | ', $group), implode(' | ', $group)], $groups));
        $similarity = $typed === []
            ? '0'
            : 'greatest('.implode(', ', array_fill(0, count($typed), 'word_similarity(?, knowledge_chunks.search_text)')).', 0)';

        $query = $this->chunks->query($mallId, $scopes, $kinds)
            ->selectRaw(
                "knowledge_chunks.chunk_id, ({$matched}) as matched, ts_rank_cd(knowledge_chunks.search_vector, ".self::TS_QUERY.", 32) as rank, {$similarity} as similarity",
                [...$matchedBindings, $any, $any, ...$typed],
            )
            ->where(function (Builder $match) use ($any, $typed): void {
                $match->whereRaw('knowledge_chunks.search_vector @@ '.self::TS_QUERY, [$any, $any]);

                foreach ($typed as $word) {
                    $match->orWhereRaw('? <% knowledge_chunks.search_text', [$word]);
                }
            })
            ->orderByDesc('matched')
            ->orderByDesc('rank')
            ->orderByDesc('similarity')
            ->orderBy('knowledge_chunks.chunk_id')
            ->limit($limit);

        // The default threshold of <% (0.6) misses one-letter slips such as "cinma" (0.5) or
        // "parkng" (0.57), so it is lowered for this transaction only; the GIN index stays usable.
        $rows = DB::transaction(function () use ($query): Collection {
            DB::select("select set_config('pg_trgm.word_similarity_threshold', ?, true)", [(string) self::FUZZY_THRESHOLD]);

            return $query->get();
        });

        $found = [];

        foreach ($rows as $row) {
            $found[(string) $row->chunk_id] = ((int) $row->matched + (float) $row->rank + 0.5 * (float) $row->similarity) / (count($groups) + 1.5);
        }

        arsort($found);

        return $found;
    }
}
