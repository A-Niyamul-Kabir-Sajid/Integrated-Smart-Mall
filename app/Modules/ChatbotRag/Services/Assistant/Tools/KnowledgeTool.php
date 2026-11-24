<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Modules\ChatbotRag\DTOs\RetrievedChunkData;
use App\Modules\ChatbotRag\DTOs\SourceSnippetData;
use App\Modules\ChatbotRag\DTOs\ToolResultData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\ChatbotRag\Services\Assistant\NavigationHandoff;
use App\Modules\ChatbotRag\Services\Retrieval\KnowledgeRetriever;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\ShopProduct;

/**
 * Answers from the knowledge documents the reader may see (Jira ISM-132). How much of the question
 * the best chunk covers decides the outcome: answered, answered with a warning, or not answered.
 *
 * A chunk about a product offer is checked against the catalogue before it is used: an offer that
 * is no longer visible is dropped, and price and availability are added from the catalogue as they
 * are now, never from the stored text.
 */
class KnowledgeTool
{
    public function __construct(
        private KnowledgeRetriever $retriever,
        private CatalogueLookup $catalogue,
        private DirectoryLookup $directory,
        private NavigationHandoff $handoff,
    ) {}

    public function run(Mall $mall, AccessScope $reader, string $question): ToolResultData
    {
        $chunks = $this->retriever->retrieve($mall, $reader, $question);
        $listings = $this->catalogue->visibleListings($mall, array_values(array_filter(array_map(
            fn (RetrievedChunkData $chunk): ?string => $chunk->shopProductId,
            $chunks,
        ))))->keyBy(fn (ShopProduct $listing): string => (string) $listing->getKey());

        $chunks = array_values(array_filter($chunks, fn (RetrievedChunkData $chunk): bool => $chunk->kind !== SourceKind::ProductListing
            || ($chunk->shopProductId !== null && $listings->has($chunk->shopProductId))));

        if ($chunks === []) {
            return ToolResultData::nothing();
        }

        $outcome = $this->outcomeFor($chunks[0]->coverage);

        if ($outcome === AnswerOutcome::NoAnswer) {
            return ToolResultData::nothing();
        }

        $usable = array_values(array_filter($chunks, fn (RetrievedChunkData $chunk): bool => $this->outcomeFor($chunk->coverage) !== AnswerOutcome::NoAnswer));
        $sources = [];
        $recommendations = [];
        $handoffs = [];

        foreach ($usable as $index => $chunk) {
            $listing = $chunk->shopProductId === null ? null : $listings->get($chunk->shopProductId);
            $text = $chunk->text;

            if ($listing !== null) {
                $text .= "\n".$this->liveFacts($listing);
                $recommendations[$chunk->shopProductId] = ['listing_id' => $chunk->shopProductId, 'reason' => $chunk->title];
            }

            $sources[] = new SourceSnippetData(
                number: $index + 1,
                kind: $chunk->kind->value,
                title: $chunk->heading === null ? $chunk->title : $chunk->title.': '.$chunk->heading,
                text: $text,
                scope: $chunk->scope,
                chunkId: $chunk->chunkId,
                score: $chunk->score,
            );

            if ($chunk->shopId !== null && ! isset($handoffs[$chunk->shopId])) {
                $shop = $listing?->shop ?? $this->listedShop($mall, $chunk->shopId);

                if ($shop !== null) {
                    $handoffs[$chunk->shopId] = $this->handoff->forShop($shop);
                }
            }
        }

        return new ToolResultData(
            outcome: $outcome,
            sources: $sources,
            recommendations: array_values($recommendations),
            handoffs: array_values($handoffs),
            meta: ['data_as_of' => min(array_map(fn (RetrievedChunkData $chunk): string => $chunk->capturedAt->toIso8601String(), $usable))],
        );
    }

    private function outcomeFor(float $coverage): AnswerOutcome
    {
        return match (true) {
            $coverage >= (float) config('assistant.retrieval.answer_coverage', 0.6) => AnswerOutcome::Answered,
            $coverage >= (float) config('assistant.retrieval.low_coverage', 0.34) => AnswerOutcome::LowConfidence,
            default => AnswerOutcome::NoAnswer,
        };
    }

    private function liveFacts(ShopProduct $listing): string
    {
        return 'Now: '.$listing->currency.' '.number_format((float) $listing->price_amount, 2).', '
            .($listing->availability_status === 'available' ? 'available.' : 'currently unavailable.');
    }

    private function listedShop(Mall $mall, string $shopId): ?Shop
    {
        return $this->directory->listedShops((string) $mall->getKey())->first(fn ($shop): bool => (string) $shop->getKey() === $shopId);
    }
}
