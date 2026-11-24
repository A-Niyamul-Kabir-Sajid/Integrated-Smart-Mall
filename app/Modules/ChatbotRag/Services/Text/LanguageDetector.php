<?php

namespace App\Modules\ChatbotRag\Services\Text;

/**
 * Decides whether a question is written in English or in Bangla, so that the answer comes back in
 * the same language. Only the script is looked at: Bangla letters mean Bangla.
 */
class LanguageDetector
{
    public const ENGLISH = 'en';

    public const BANGLA = 'bn';

    /**
     * @param  string|null  $fallback  The language to use when the text has no letters (a number, an emoji)
     */
    public function detect(string $text, ?string $fallback = null): string
    {
        $bangla = preg_match_all('/[\x{0980}-\x{09FF}]/u', $text);
        $latin = preg_match_all('/[A-Za-z]/', $text);

        if ($bangla === 0 && $latin === 0) {
            return in_array($fallback, [self::ENGLISH, self::BANGLA], true) ? $fallback : self::ENGLISH;
        }

        return $bangla * 2 >= $latin ? self::BANGLA : self::ENGLISH;
    }

    /**
     * The language's own English name, for an instruction to a model.
     */
    public function name(string $language): string
    {
        return $language === self::BANGLA ? 'Bangla (Bengali)' : 'English';
    }
}
