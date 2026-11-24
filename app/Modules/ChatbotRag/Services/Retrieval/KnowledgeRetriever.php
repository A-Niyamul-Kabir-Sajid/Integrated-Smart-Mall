<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

use App\Modules\ChatbotRag\Contracts\ChunkSearch;
use App\Modules\ChatbotRag\DTOs\RetrievedChunkData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use App\Modules\MallShopDirectory\Models\Mall;
use Carbon\CarbonImmutable;

/**
 * Finds the chunks that may answer a question in one mall for one kind of reader (Jira ISM-130).
 *
 * The database search proposes candidates; this class loads them again through the access filter,
 * so a chunk the reader may not see is dropped even if a search returned it, and measures how much
 * of the question each one covers. The coverage, not the database rank, decides whether the
 * assistant answers, answers with a warning or says it does not know.
 */
class KnowledgeRetriever
{
    /**
     * How a chunk's score is made up. Coverage of the question decides; the database's own rank
     * only orders chunks that cover it equally; and between those, a document the mall wrote comes
     * before a shop's profile, a profile before the floor guide, and the guide before a single offer.
     */
    private const COVERAGE_WEIGHT = 0.9;

    private const RANK_WEIGHT = 0.05;

    private const WRITTEN_PREFERENCE = 0.05;

    private const CAPTURED_PREFERENCE = ['shop_profile' => 0.03, 'mall_guide' => 0.02, 'product_listing' => 0.0];

    public function __construct(
        private ChunkSearch $search,
        private RetrievableChunks $chunks,
        private TextAnalyzer $analyzer,
        private QueryExpander $expander,
        private TermMatcher $matcher,
    ) {}

    /**
     * @param  list<SourceKind>|null  $kinds  Source kinds to search, or null for all
     * @return list<RetrievedChunkData> Best first; empty when nothing matches
     */
    public function retrieve(Mall $mall, AccessScope $reader, string $question, ?int $limit = null, ?array $kinds = null): array
    {
        $mallId = (string) $mall->getKey();
        $scopes = $reader->readableScopes();
        $kindValues = $kinds === null ? null : array_map(fn (SourceKind $kind): string => $kind->value, $kinds);
        $alternatives = $this->alternativesByTerm($question);

        if ($alternatives === []) {
            return [];
        }

        $candidates = $this->search->search($mallId, $scopes, $this->wordGroups($question), $kindValues, (int) config('assistant.retrieval.candidates', 40));

        if ($candidates === []) {
            return [];
        }

        $rows = $this->chunks->query($mallId, $scopes, $kindValues)
            ->whereIn('knowledge_chunks.chunk_id', array_keys($candidates))
            ->get([
                'knowledge_chunks.chunk_id', 'knowledge_chunks.heading', 'knowledge_chunks.chunk_text', 'knowledge_chunks.search_text',
                'knowledge_documents.document_id', 'knowledge_documents.title', 'knowledge_documents.source_kind', 'knowledge_documents.access_scope',
                'knowledge_documents.source_locator', 'knowledge_documents.shop_id', 'knowledge_documents.shop_product_id',
                'document_revisions.revision_no', 'document_revisions.captured_at',
            ]);

        $best = max($candidates) ?: 1.0;
        $found = [];

        foreach ($rows as $row) {
            $coverage = $this->coverage(
                $alternatives,
                $this->analyzer->termCounts((string) $row->search_text),
                array_fill_keys(explode(' ', (string) $row->search_text), true),
            );

            if ($coverage <= 0.0) {
                continue;
            }

            $found[] = new RetrievedChunkData(
                chunkId: (string) $row->chunk_id,
                documentId: (string) $row->document_id,
                title: (string) $row->title,
                heading: $row->heading,
                text: (string) $row->chunk_text,
                kind: SourceKind::from((string) $row->source_kind),
                scope: AccessScope::from((string) $row->access_scope),
                locator: (string) $row->source_locator,
                shopId: $row->shop_id === null ? null : (string) $row->shop_id,
                shopProductId: $row->shop_product_id === null ? null : (string) $row->shop_product_id,
                revisionNo: (int) $row->revision_no,
                capturedAt: CarbonImmutable::parse((string) $row->captured_at),
                score: round(self::COVERAGE_WEIGHT * $coverage + self::RANK_WEIGHT * ($candidates[(string) $row->chunk_id] / $best) + $this->preference((string) $row->source_locator, (string) $row->source_kind), 4),
                coverage: round($coverage, 4),
            );
        }

        usort($found, fn (RetrievedChunkData $a, RetrievedChunkData $b): int => [$b->score, $a->chunkId] <=> [$a->score, $b->chunkId]);

        return array_slice($found, 0, $limit ?? (int) config('assistant.retrieval.top', 4));
    }

    private function preference(string $locator, string $kind): float
    {
        return str_starts_with($locator, KnowledgeDocument::SYSTEM_PREFIX) ? (self::CAPTURED_PREFERENCE[$kind] ?? 0.0) : self::WRITTEN_PREFERENCE;
    }

    /**
     * What is handed to the database search: each content word of the question with its synonyms.
     *
     * @return list<list<string>>
     */
    private function wordGroups(string $question): array
    {
        return array_map(
            fn (string $word): array => array_values(array_unique([$word, ...$this->expander->alternatives($word)])),
            $this->analyzer->contentWords($question),
        );
    }

    /**
     * Each content word of the question with its stem and the stems that mean the same.
     *
     * @return array<string, array{stem: string, synonyms: list<string>}> Keyed by the word as typed
     */
    private function alternativesByTerm(string $question): array
    {
        $terms = [];

        foreach ($this->analyzer->contentWords($question) as $word) {
            $terms[$word] = [
                'stem' => $this->analyzer->stem($word),
                'synonyms' => array_values(array_unique(array_map($this->analyzer->stem(...), $this->expander->alternatives($word)))),
            ];
        }

        return $terms;
    }

    /**
     * Share of the question's words found in the chunk. A synonym counts fully, a near match (a
     * spelling mistake in the word the visitor typed) a little less.
     *
     * @param  array<string, array{stem: string, synonyms: list<string>}>  $alternatives
     * @param  array<string, int>  $chunkTerms
     * @param  array<string, true>  $chunkWords
     */
    private function coverage(array $alternatives, array $chunkTerms, array $chunkWords): float
    {
        $matched = 0.0;

        foreach ($alternatives as $word => $term) {
            $best = $this->matcher->match((string) $word, $term['stem'], $chunkTerms, $chunkWords);

            foreach ($term['synonyms'] as $synonym) {
                if ($best < TermMatcher::EXACT && isset($chunkTerms[$synonym])) {
                    $best = TermMatcher::EXACT;
                }
            }

            $matched += $best;
        }

        return $matched / count($alternatives);
    }
}
