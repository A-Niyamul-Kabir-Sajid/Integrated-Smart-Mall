<?php

namespace App\Modules\ChatbotRag\DTOs;

use App\Modules\ChatbotRag\Enums\AccessScope;

/**
 * One numbered piece of context an answer may rest on: a retrieved chunk of a knowledge document,
 * or a line read live from the catalogue or the directory. The number is what an answer cites.
 */
final readonly class SourceSnippetData
{
    public const CATALOGUE = 'catalogue';

    public const DIRECTORY = 'directory';

    public const FACILITY = 'facility';

    /**
     * @param  string  $kind  A SourceKind value for a chunk, otherwise one of the constants above
     * @param  string|null  $chunkId  Set when the snippet is a chunk, so the answer can cite it
     */
    public function __construct(
        public int $number,
        public string $kind,
        public string $title,
        public string $text,
        public AccessScope $scope = AccessScope::Public,
        public ?string $chunkId = null,
        public ?float $score = null,
    ) {}

    public function isPublic(): bool
    {
        return $this->scope === AccessScope::Public;
    }
}
