<?php

namespace App\Modules\ChatbotRag\Services\Generation;

use App\Modules\ChatbotRag\Contracts\AnswerGenerator;
use App\Modules\ChatbotRag\DTOs\GeneratedAnswerData;
use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\Exceptions\GenerationUnavailable;
use Illuminate\Support\Facades\Log;

/**
 * The generator the assistant uses (decision D-018): the model driver when one is configured, and
 * the template driver whenever the model cannot answer (no key, API failure, rate limit) or its
 * answer does not pass the validator. A visitor always gets an answer from the sources; which
 * driver wrote it, and why the model was skipped, is recorded with the answer.
 */
class FallbackAnswerGenerator implements AnswerGenerator
{
    public const INVALID_OUTPUT = 'invalid_output';

    public function __construct(
        private ?AnswerGenerator $model,
        private TemplateAnswerGenerator $template,
        private GeneratedAnswerValidator $validator,
    ) {}

    public function generate(GenerationRequestData $request): GeneratedAnswerData
    {
        if ($this->model === null) {
            return $this->template->generate($request);
        }

        try {
            $answer = $this->model->generate($request);
            $problem = $this->validator->problem($request, $answer);

            if ($problem === null) {
                return $answer;
            }

            $reason = self::INVALID_OUTPUT;
            $context = ['problem' => $problem];
        } catch (GenerationUnavailable $unavailable) {
            $reason = $unavailable->reason;
            $context = $unavailable->context;
        }

        // Codes only: never the key, the question or the model's text.
        Log::notice('The assistant answered with the template driver.', ['reason' => $reason, ...$context]);

        return $this->template->generate($request)->becauseOf($reason);
    }
}
