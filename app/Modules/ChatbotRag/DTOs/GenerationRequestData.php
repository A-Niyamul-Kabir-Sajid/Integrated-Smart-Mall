<?php

namespace App\Modules\ChatbotRag\DTOs;

/**
 * What an answer generator receives: the question (personal details already removed), the language
 * to answer in, and the numbered sources. Nothing else about the visitor, the conversation or the
 * mall's records is ever part of it.
 */
final readonly class GenerationRequestData
{
    /** The sources are passages of documents; the answer quotes what answers the question. */
    public const PASSAGES = 'passages';

    /** The sources are a list of results (offers, shops, facilities); the answer presents them. */
    public const LISTING = 'listing';

    /**
     * @param  string  $language  "en" or "bn"
     * @param  list<SourceSnippetData>  $sources
     * @param  string|null  $lead  A sentence that must open the answer, such as how the question was understood
     * @param  bool  $uncertain  The sources are the closest found but may not answer the question
     */
    public function __construct(
        public string $question,
        public string $language,
        public string $mallName,
        public array $sources,
        public string $shape = self::PASSAGES,
        public ?string $lead = null,
        public bool $uncertain = false,
    ) {}

    /**
     * @return list<int>
     */
    public function sourceNumbers(): array
    {
        return array_map(fn (SourceSnippetData $source): int => $source->number, $this->sources);
    }
}
