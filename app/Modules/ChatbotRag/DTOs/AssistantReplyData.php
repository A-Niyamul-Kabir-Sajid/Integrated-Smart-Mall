<?php

namespace App\Modules\ChatbotRag\DTOs;

use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Enums\AssistantTool;

/**
 * The assistant's reply to one question: the text, how it ended, and everything it rests on.
 */
final readonly class AssistantReplyData
{
    /**
     * @param  string  $question  The question as it is stored: personal details removed
     * @param  AssistantTool|null  $tool  The tool that answered; null when the question was refused before routing
     * @param  string  $language  "en" or "bn"
     * @param  string  $driver  The driver that wrote the text
     * @param  list<array{chunk_id: string, number: int, score: float|null, excerpt: string}>  $citations  Chunks the answer cites
     * @param  list<array{listing_id: string, reason: string}>  $recommendations
     * @param  array<string, mixed>  $meta  Stored with the message
     */
    public function __construct(
        public string $question,
        public string $text,
        public AnswerOutcome $outcome,
        public ?AssistantTool $tool,
        public string $language,
        public string $driver,
        public ?string $modelLabel = null,
        public ?string $fallbackReason = null,
        public ?string $refusalReason = null,
        public array $citations = [],
        public array $recommendations = [],
        public array $meta = [],
    ) {}
}
