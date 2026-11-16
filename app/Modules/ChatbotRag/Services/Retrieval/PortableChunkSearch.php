<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

use App\Modules\ChatbotRag\Contracts\ChunkSearch;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;

/**
 * Retrieval on databases without PostgreSQL's text search (the SQLite test and scratch databases).
 * It reads the retrievable chunks of the mall, at most SCAN_LIMIT, and compares words in PHP: a
 * chunk scores by the share of the question's words it holds, a synonym counting as the word. The
 * same access filter applies as on PostgreSQL; only the matching differs. Not meant for production.
 */
class PortableChunkSearch implements ChunkSearch
{
    public const SCAN_LIMIT = 2000;

    public function __construct(
        private RetrievableChunks $chunks,
        private TextAnalyzer $analyzer,
        private TermMatcher $matcher,
    ) {}

    public function search(string $mallId, array $scopes, array $wordGroups, ?array $kinds, int $limit): array
    {
        $wordGroups = array_values(array_filter($wordGroups));

        if ($wordGroups === [] || $scopes === []) {
            return [];
        }

        $rows = $this->chunks->query($mallId, $scopes, $kinds)
            ->orderBy('knowledge_chunks.chunk_id')
            ->limit(self::SCAN_LIMIT)
            ->get(['knowledge_chunks.chunk_id', 'knowledge_chunks.search_text']);

        $found = [];

        foreach ($rows as $row) {
            $chunkTerms = $this->analyzer->termCounts((string) $row->search_text);
            $chunkWords = array_fill_keys(explode(' ', (string) $row->search_text), true);
            $score = 0.0;

            foreach ($wordGroups as $group) {
                $best = 0.0;

                foreach ($group as $index => $word) {
                    // A spelling mistake is forgiven in the word the visitor typed, not in its synonyms.
                    $best = max($best, $index === 0
                        ? $this->matcher->match($word, $this->analyzer->stem($word), $chunkTerms, $chunkWords)
                        : (isset($chunkTerms[$this->analyzer->stem($word)]) ? TermMatcher::EXACT : 0.0));
                }

                $score += $best;
            }

            if ($score > 0) {
                $found[(string) $row->chunk_id] = $score / count($wordGroups);
            }
        }

        // Equal scores keep the chunk-id order of the query, so the result does not depend on the sort.
        uksort($found, fn (string $a, string $b): int => [$found[$b], $a] <=> [$found[$a], $b]);

        return array_slice($found, 0, $limit, true);
    }
}
