<?php

namespace App\Modules\ChatbotRag\DTOs;

use App\Modules\ChatbotRag\Enums\AnswerOutcome;

/**
 * What a tool found for a question, before any text is written from it.
 */
final readonly class ToolResultData
{
    /**
     * @param  list<SourceSnippetData>  $sources  Numbered context the answer may rest on
     * @param  string  $shape  GenerationRequestData::PASSAGES or ::LISTING
     * @param  string|null  $lead  A sentence that opens the answer (how the question was understood)
     * @param  string|null  $fixedText  When set, this is the whole answer and no generator runs
     * @param  list<array{listing_id: string, reason: string}>  $recommendations  Listings to recommend, best first
     * @param  list<array{type: string, id: string, label: string, detail: string|null}>  $handoffs  Destinations to offer directions to
     * @param  array<string, mixed>  $meta  Kept with the answer: filters used, notes, links
     */
    public function __construct(
        public AnswerOutcome $outcome,
        public array $sources = [],
        public string $shape = GenerationRequestData::PASSAGES,
        public ?string $lead = null,
        public ?string $fixedText = null,
        public array $recommendations = [],
        public array $handoffs = [],
        public array $meta = [],
    ) {}

    public static function nothing(): self
    {
        return new self(AnswerOutcome::NoAnswer);
    }
}
