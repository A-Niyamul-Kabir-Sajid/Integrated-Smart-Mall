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

        // Seven or more digits, possibly grouped with spaces, dashes, dots or brackets. A date written
        // as year-month-day is not a personal number and stays.
        return (string) preg_replace_callback(
            '/(?<![\p{L}\p{N}])\+?\p{Nd}(?:[\s\-().]{0,2}\p{Nd}){6,}(?![\p{L}\p{N}])/u',
            fn (array $match): string => preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $match[0]) === 1 ? $match[0] : self::NUMBER,
            $text,
        );
    }

    /**
     * A masked text without the placeholders, for searching: "[email removed]" is not something
     * the visitor asked about.
     */
    public function withoutPlaceholders(string $masked): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace([self::EMAIL, self::NUMBER], ' ', $masked)));
    }
}
