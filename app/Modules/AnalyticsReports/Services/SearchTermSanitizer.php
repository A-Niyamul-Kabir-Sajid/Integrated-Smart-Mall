<?php

namespace App\Modules\AnalyticsReports\Services;

/**
 * Turns what a visitor typed into a countable search term, and refuses to keep anything that looks
 * like personal data. "Coffee ", "coffee" and "COFFEE" become one term; an email address or a run of
 * seven or more digits (a phone number) is not stored at all, only counted as a masked search.
 */
class SearchTermSanitizer
{
    public const MIN_PHONE_DIGITS = 7;

    /**
     * @return array{term: ?string, masked: bool} term is null when the text was empty or masked
     */
    public function sanitize(?string $text): array
    {
        $text = mb_strtolower(trim((string) $text));
        $text = (string) preg_replace('/[\p{C}\s]+/u', ' ', $text);
        $text = trim($text);

        if ($text === '') {
            return ['term' => null, 'masked' => false];
        }

        if ($this->looksPersonal($text)) {
            return ['term' => null, 'masked' => true];
        }

        return ['term' => mb_substr($text, 0, (int) config('analytics.term_max', 100)), 'masked' => false];
    }

    private function looksPersonal(string $text): bool
    {
        return str_contains($text, '@')
            || strlen((string) preg_replace('/\D+/', '', $text)) >= self::MIN_PHONE_DIGITS;
    }
}
