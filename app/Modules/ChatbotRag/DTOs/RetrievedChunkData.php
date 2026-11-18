<?php

namespace App\Modules\ChatbotRag\DTOs;

use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\SourceKind;
use Carbon\CarbonImmutable;

/**
 * One chunk retrieval found, with the source it must be attributed to.
 */
final readonly class RetrievedChunkData
{
    /**
     * @param  float  $score  Ranking value, 0 to 1; only comparable within one retrieval
     * @param  float  $coverage  Share of the question's words this chunk contains, 0 to 1
     */
    public function __construct(
        public string $chunkId,
        public string $documentId,
        public string $title,
        public ?string $heading,
        public string $text,
        public SourceKind $kind,
        public AccessScope $scope,
        public string $locator,
        public ?string $shopId,
        public ?string $shopProductId,
        public int $revisionNo,
        public CarbonImmutable $capturedAt,
        public float $score,
        public float $coverage,
    ) {}
}
