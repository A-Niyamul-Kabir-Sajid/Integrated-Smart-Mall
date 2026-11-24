<?php

namespace App\Modules\ChatbotRag\Services\Evaluation;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\DTOs\AssistantReplyData;
use App\Modules\ChatbotRag\DTOs\RetrievedChunkData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Models\KnowledgeChunk;
use App\Modules\ChatbotRag\Services\Assistant\AssistantService;
use App\Modules\ChatbotRag\Services\Retrieval\KnowledgeRetriever;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\ShopProduct;

/**
 * Runs a set of questions with known right answers through the assistant and measures what the
 * epic asks for (Jira ISM-136): factuality, retrieval quality, refusal and uncertainty, and access
 * control. Nothing is stored: questions are answered without a conversation.
 *
 * Besides what each case expects, every answer is checked the same way, whatever the case says:
 * a quoted excerpt must be in the chunk it cites, a cited document must be one this kind of
 * conversation may read, and a recommended offer must be on sale in the catalogue. A failure of one
 * of these is counted separately, because it would mean the assistant made something up or showed
 * something it must not.
 */
class EvaluationRunner
{
    public const GROUPS = ['grounding', 'catalogue', 'directory', 'uncertainty', 'refusal', 'access', 'admin'];

    public function __construct(
        private AssistantService $assistant,
        private KnowledgeRetriever $retriever,
        private CatalogueLookup $catalogue,
    ) {}

    /**
     * @param  list<array{id: string, group: string, scope: string, question: string, expect: array<string, mixed>}>  $cases
     * @param  User|null  $administrator  Asks the cases of the administrators' assistant; without one they are skipped
     * @return array{cases: list<array{id: string, group: string, scope: string, question: string, status: string, outcome: string|null, tool: string|null, problems: list<string>}>, summary: array<string, mixed>}
     */
    public function run(Mall $mall, array $cases, ?User $administrator = null): array
    {
        $results = [];
        $ranks = [];
        $violations = ['unsupported_citations' => 0, 'fabricated_offers' => 0, 'access_violations' => 0];

        foreach ($cases as $case) {
            $scope = AccessScope::from($case['scope']);

            if ($scope === AccessScope::MallAdmin && $administrator === null) {
                $results[] = [...$this->describe($case), 'status' => 'skipped', 'outcome' => null, 'tool' => null, 'problems' => ['No administrator of the mall to ask as.']];

                continue;
            }

            $reply = $this->assistant->respond($mall, $scope, $scope === AccessScope::MallAdmin ? $administrator : null, $case['question']);
            $citedTitles = [];
            $problems = [
                ...$this->integrityProblems($mall, $scope, $reply, $violations, $citedTitles),
                ...$this->expectationProblems($mall, $case['expect'], $reply, $citedTitles),
            ];

            if (isset($case['expect']['source'])) {
                $ranks[] = $this->rankOf($case['expect']['source'], $this->retriever->retrieve($mall, $scope, $case['question'], 5));
            }

            $results[] = [...$this->describe($case), 'status' => $problems === [] ? 'passed' : 'failed', 'outcome' => $reply->outcome->value, 'tool' => $reply->tool?->value, 'problems' => $problems];
        }

        return ['cases' => $results, 'summary' => $this->summarise($results, $ranks, $violations)];
    }

    /**
     * @param  array{id: string, group: string, scope: string, question: string}  $case
     * @return array{id: string, group: string, scope: string, question: string}
     */
    private function describe(array $case): array
    {
        return ['id' => $case['id'], 'group' => $case['group'], 'scope' => $case['scope'], 'question' => $case['question']];
    }

    /**
     * The checks every answer must pass.
     *
     * @param  array{unsupported_citations: int, fabricated_offers: int, access_violations: int}  $violations
     * @param  list<string>  $citedTitles  Filled with the titles of the cited documents
     * @return list<string>
     */
    private function integrityProblems(Mall $mall, AccessScope $scope, AssistantReplyData $reply, array &$violations, array &$citedTitles): array
    {
        $problems = [];
        $chunks = KnowledgeChunk::query()->whereKey(array_column($reply->citations, 'chunk_id'))->with('revision.document')->get()->keyBy('chunk_id');

        foreach ($reply->citations as $citation) {
            $chunk = $chunks->get($citation['chunk_id']);
            $document = $chunk?->revision->document;

            if ($chunk === null || $document === null) {
                $violations['unsupported_citations']++;
                $problems[] = 'A citation points at a chunk that does not exist.';

                continue;
            }

            $citedTitles[] = $document->title;

            if ($document->mall_id !== $mall->getKey() || ! in_array($document->access_scope->value, $scope->readableScopes(), true)) {
                $violations['access_violations']++;
                $problems[] = "Cited \"{$document->title}\", which this kind of conversation may not read.";
            }

            if (! $this->isQuotedFrom($citation['excerpt'], $chunk->chunk_text)) {
                $violations['unsupported_citations']++;
                $problems[] = "The excerpt quoted from \"{$document->title}\" is not in that document.";
            }
        }

        $listed = $reply->recommendations === [] ? [] : $this->catalogue->visibleListings($mall, array_column($reply->recommendations, 'listing_id'))
            ->map(fn (ShopProduct $listing): string => (string) $listing->getKey())->all();

        foreach ($reply->recommendations as $recommendation) {
            if (! in_array($recommendation['listing_id'], $listed, true)) {
                $violations['fabricated_offers']++;
                $problems[] = 'A recommended offer is not on sale in the catalogue.';
            }
        }

        return $problems;
    }

    /**
     * What this case expects of the answer.
     *
     * @param  array<string, mixed>  $expect
     * @param  list<string>  $citedTitles
     * @return list<string>
     */
    private function expectationProblems(Mall $mall, array $expect, AssistantReplyData $reply, array $citedTitles): array
    {
        $problems = [];

        if (isset($expect['outcome']) && ! in_array($reply->outcome->value, (array) $expect['outcome'], true)) {
            $problems[] = 'Outcome was '.$reply->outcome->value.', expected '.implode(' or ', (array) $expect['outcome']).'.';
        }

        if (isset($expect['tool']) && $reply->tool?->value !== $expect['tool']) {
            $problems[] = 'Tool was '.($reply->tool?->value ?? 'none').', expected '.$expect['tool'].'.';
        }

        if (isset($expect['refusal']) && $reply->refusalReason !== $expect['refusal']) {
            $problems[] = 'Refusal reason was '.($reply->refusalReason ?? 'none').', expected '.$expect['refusal'].'.';
        }

        if (isset($expect['source']) && ! in_array($expect['source'], $citedTitles, true)) {
            $problems[] = 'Did not cite "'.$expect['source'].'".';
        }

        foreach ($expect['contains'] ?? [] as $needle) {
            if (! str_contains($reply->text, $needle)) {
                $problems[] = 'The answer does not contain "'.$needle.'".';
            }
        }

        foreach ($expect['not_contains'] ?? [] as $needle) {
            if (str_contains(mb_strtolower($reply->text), mb_strtolower($needle))) {
                $problems[] = 'The answer contains "'.$needle.'", which it must not.';
            }
        }

        if (isset($expect['items'])) {
            $names = $reply->recommendations === [] ? [] : $this->catalogue->visibleListings($mall, array_column($reply->recommendations, 'listing_id'))
                ->map(fn (ShopProduct $listing): string => $listing->variant->product->name)->all();

            foreach ($expect['items'] as $name) {
                if (! in_array($name, $names, true)) {
                    $problems[] = 'Did not recommend "'.$name.'".';
                }
            }
        }

        if (($expect['no_items'] ?? false) && $reply->recommendations !== []) {
            $problems[] = 'Recommended an offer where none was expected.';
        }

        if (isset($expect['directions']) && ! in_array($expect['directions'], array_column($reply->meta['handoffs'] ?? [], 'label'), true)) {
            $problems[] = 'Offered no directions to "'.$expect['directions'].'".';
        }

        return $problems;
    }

    /**
     * The position (from 1) of the first retrieved chunk of the expected document, or null.
     *
     * @param  list<RetrievedChunkData>  $retrieved
     */
    private function rankOf(string $title, array $retrieved): ?int
    {
        foreach ($retrieved as $index => $chunk) {
            if ($chunk->title === $title) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: string, group: string, scope: string, question: string, status: string, outcome: string|null, tool: string|null, problems: list<string>}>  $results
     * @param  list<int|null>  $ranks
     * @param  array{unsupported_citations: int, fabricated_offers: int, access_violations: int}  $violations
     * @return array<string, mixed>
     */
    private function summarise(array $results, array $ranks, array $violations): array
    {
        $groups = [];

        foreach ($results as $result) {
            $groups[$result['group']] ??= ['passed' => 0, 'failed' => 0, 'skipped' => 0];
            $groups[$result['group']][$result['status']]++;
        }

        $found = array_values(array_filter($ranks, fn (?int $rank): bool => $rank !== null));
        $share = fn (int $count): ?float => $ranks === [] ? null : round($count / count($ranks), 3);

        return [
            'total' => count($results),
            'passed' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'passed')),
            'failed' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'failed')),
            'skipped' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'skipped')),
            'groups' => $groups,
            'retrieval' => [
                'questions' => count($ranks),
                'hit_at_1' => $share(count(array_filter($found, fn (int $rank): bool => $rank === 1))),
                'hit_at_3' => $share(count(array_filter($found, fn (int $rank): bool => $rank <= 3))),
                'mrr' => $ranks === [] ? null : round(array_sum(array_map(fn (int $rank): float => 1 / $rank, $found)) / count($ranks), 3),
            ],
            ...$violations,
        ];
    }

    /**
     * Whether every sentence of the excerpt stands in the chunk word for word. An excerpt may leave
     * sentences out and may end with "..." where it was cut; it may not add or change a word.
     */
    private function isQuotedFrom(string $excerpt, string $chunkText): bool
    {
        $chunk = $this->squash($chunkText);
        $excerpt = (string) preg_replace('/\.\.\.$/', '', $this->squash($excerpt));

        foreach (preg_split('/(?<=[.!?।])\s+/u', $excerpt) ?: [] as $sentence) {
            if (trim($sentence) !== '' && ! str_contains($chunk, trim($sentence))) {
                return false;
            }
        }

        return true;
    }

    private function squash(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
