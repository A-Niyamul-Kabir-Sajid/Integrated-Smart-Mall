<?php

namespace App\Modules\ChatbotRag\Contracts;

use App\Modules\ChatbotRag\DTOs\GeneratedAnswerData;
use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\Exceptions\GenerationUnavailable;

/**
 * Writes the text of an answer from a question and numbered sources (decision D-018). Drivers: a
 * language model behind an API, and a template driver that needs no key and always answers. A driver
 * receives nothing but the request, so it can only ever see the context the assistant retrieved.
 */
interface AnswerGenerator
{
    /**
     * @throws GenerationUnavailable When this driver cannot answer now; the caller falls back to the template driver
     */
    public function generate(GenerationRequestData $request): GeneratedAnswerData;
}
