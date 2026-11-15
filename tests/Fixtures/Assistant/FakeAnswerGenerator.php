<?php

namespace Tests\Fixtures\Assistant;

use App\Modules\ChatbotRag\Contracts\AnswerGenerator;
use App\Modules\ChatbotRag\DTOs\GeneratedAnswerData;
use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\Exceptions\GenerationUnavailable;
use Closure;

/**
 * A model driver for tests: it calls no API, remembers every request it was given and answers with
 * what the test tells it to (a fixed answer, a closure over the request, or a failure).
 */
class FakeAnswerGenerator implements AnswerGenerator
{
    public const MODEL = 'fake-model-1';

    /** @var list<GenerationRequestData> */
    public array $requests = [];

    public function __construct(private Closure|GeneratedAnswerData|GenerationUnavailable $reply) {}

    /**
     * A model that answers with this text and cites these sources.
     *
     * @param  list<int>  $cited
     */
    public static function answering(string $text, array $cited = [1]): self
    {
        return new self(new GeneratedAnswerData($text, $cited, true, GeneratedAnswerData::GEMINI, self::MODEL));
    }

    /**
     * A model that finds the sources do not answer the question.
     */
    public static function notKnowing(): self
    {
        return new self(new GeneratedAnswerData('', [], false, GeneratedAnswerData::GEMINI, self::MODEL));
    }

    public static function failing(string $reason): self
    {
        return new self(new GenerationUnavailable($reason));
    }

    public function generate(GenerationRequestData $request): GeneratedAnswerData
    {
        $this->requests[] = $request;

        if ($this->reply instanceof GenerationUnavailable) {
            throw $this->reply;
        }

        return $this->reply instanceof Closure ? ($this->reply)($request) : $this->reply;
    }
}
