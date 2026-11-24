<?php

namespace App\Modules\ChatbotRag\DTOs;

/**
 * The answer a generator produced from a request.
 */
final readonly class GeneratedAnswerData
{
    public const TEMPLATE = 'template';

    public const GEMINI = 'gemini';

    /**
     * @param  list<int>  $cited  Numbers of the sources the answer rests on
     * @param  bool  $answerable  False when the generator found that the sources do not answer the question
     * @param  string  $driver  Which driver wrote the text
     * @param  string|null  $modelLabel  Model and version for a generated text, as stored with the message
     * @param  string|null  $fallbackReason  Why the template driver answered instead of the model, if it did
     */
    public function __construct(
        public string $text,
        public array $cited,
        public bool $answerable,
        public string $driver,
        public ?string $modelLabel = null,
        public ?string $fallbackReason = null,
    ) {}

    public function becauseOf(?string $fallbackReason): self
    {
        return new self($this->text, $this->cited, $this->answerable, $this->driver, $this->modelLabel, $fallbackReason);
    }
}
