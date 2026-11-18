<?php

namespace App\Modules\ChatbotRag\Services\Generation;

use App\Modules\ChatbotRag\DTOs\GeneratedAnswerData;
use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\Services\Text\LanguageDetector;

/**
 * Checks a model's answer before it is shown. A model is asked to use only the sources, but asking
 * is not enforcing: an answer is accepted only when it names sources it was given, states no figure
 * that is not in them, is in the visitor's language and has a sensible length. Anything else is
 * discarded and the template driver answers.
 */
class GeneratedAnswerValidator
{
    public const EMPTY = 'empty';

    public const TOO_LONG = 'too_long';

    public const UNKNOWN_SOURCE = 'unknown_source';

    public const UNCITED = 'uncited';

    public const UNSUPPORTED_FIGURE = 'unsupported_figure';

    public const WRONG_LANGUAGE = 'wrong_language';

    private const BANGLA_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    public function __construct(private LanguageDetector $languages) {}

    /**
     * @return string|null The reason the answer is rejected, or null when it may be shown
     */
    public function problem(GenerationRequestData $request, GeneratedAnswerData $answer): ?string
    {
        if (array_diff($answer->cited, $request->sourceNumbers()) !== []) {
            return self::UNKNOWN_SOURCE;
        }

        if (! $answer->answerable) {
            return null;
        }

        if (trim($answer->text) === '') {
            return self::EMPTY;
        }

        if (mb_strlen($answer->text) > (int) config('assistant.max_answer_characters', 1200)) {
            return self::TOO_LONG;
        }

        if ($answer->cited === []) {
            return self::UNCITED;
        }

        if ($this->languages->detect($answer->text, $request->language) !== $request->language) {
            return self::WRONG_LANGUAGE;
        }

        return $this->hasUnsupportedFigure($request, $answer->text) ? self::UNSUPPORTED_FIGURE : null;
    }

    /**
     * True when the answer states a number of two or more digits that is in neither the sources,
     * the lead sentence nor the question. Single digits are allowed: they count things ("3 shops").
     */
    private function hasUnsupportedFigure(GenerationRequestData $request, string $text): bool
    {
        $given = $request->question.' '.($request->lead ?? '');

        foreach ($request->sources as $source) {
            $given .= ' '.$source->title.' '.$source->text;
        }

        $allowed = array_fill_keys($this->figures($given), true);

        foreach ($this->figures((string) preg_replace('/\[\d+\]/', ' ', $text)) as $figure) {
            if (! isset($allowed[$figure]) && strlen(ltrim($figure, '0')) >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every number in the text in one spelling: ASCII digits, no thousands separators, no trailing
     * zeros after the decimal point ("1,450.00" and "১৪৫০" are both "1450").
     *
     * @return list<string>
     */
    private function figures(string $text): array
    {
        preg_match_all('/\d+(?:,\d{3})*(?:\.\d+)?/', strtr($text, self::BANGLA_DIGITS), $matches);

        return array_map(function (string $figure): string {
            $figure = str_replace(',', '', $figure);

            return str_contains($figure, '.') ? rtrim(rtrim($figure, '0'), '.') : $figure;
        }, $matches[0]);
    }
}
