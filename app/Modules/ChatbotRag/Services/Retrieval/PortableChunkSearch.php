<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

use App\Modules\ChatbotRag\Contracts\ChunkSearch;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;

/**
 * Retrieval on databases without PostgreSQL's text search (the SQLite test and scratch databases).
 * It reads the retrievable chunks of the mall, at most SCAN_LIMIT, and compares words in PHP. The
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

    public function search(string $mallId, array $scopes, array $words, ?array $kinds, int $limit): array
    {
        if ($words === [] || $scopes === []) {
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

            foreach ($words as $word) {
                $score += $this->matcher->match($word, $this->analyzer->stem($word), $chunkTerms, $chunkWords);
            }

            if ($score > 0) {
                $found[(string) $row->chunk_id] = $score / count($words);
            }
        }

        arsort($found);

        return array_slice($found, 0, $limit, true);
    }
}
