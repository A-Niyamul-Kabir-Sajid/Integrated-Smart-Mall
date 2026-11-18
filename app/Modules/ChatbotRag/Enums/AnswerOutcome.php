<?php

namespace App\Modules\ChatbotRag\Enums;

/**
 * How a question ended.
 */
enum AnswerOutcome: string
{
    /** Answered from sources that cover the question. */
    case Answered = 'answered';

    /** Answered from the closest sources, with a warning that they may not fit. */
    case LowConfidence = 'low_confidence';

    /** Nothing relevant was found; the assistant said so instead of guessing. */
    case NoAnswer = 'no_answer';

    /** The question asked for something the assistant must not do or tell. */
    case Refused = 'refused';

    public function gaveAnAnswer(): bool
    {
        return $this === self::Answered || $this === self::LowConfidence;
    }
}
