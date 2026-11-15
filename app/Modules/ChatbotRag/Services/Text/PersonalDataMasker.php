<?php

namespace App\Modules\ChatbotRag\Services\Text;

/**
 * Removes what looks like an email address or a phone, card or account number from a question
 * before it is stored, logged or sent to a model. The assistant never needs either to answer.
 */
class PersonalDataMasker
{
    public const EMAIL = '[email removed]';

    public const NUMBER = '[number removed]';

    public function mask(string $text): string
    {
        $text = (string) preg_replace('/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.\p{L}{2,}/u', self::EMAIL, $text);

        // Seven or more digits, possibly grouped with spaces, dashes, dots or brackets.
        return (string) preg_replace('/(?<![\p{L}\p{N}])\+?\p{Nd}(?:[\s\-().]{0,2}\p{Nd}){6,}(?![\p{L}\p{N}])/u', self::NUMBER, $text);
    }
}
