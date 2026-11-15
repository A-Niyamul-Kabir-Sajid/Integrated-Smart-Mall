<?php

namespace App\Modules\ChatbotRag\Services\Generation;

use App\Modules\ChatbotRag\Contracts\AnswerGenerator;
use App\Modules\ChatbotRag\DTOs\GeneratedAnswerData;
use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\DTOs\SourceSnippetData;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;

/**
 * The driver that needs no key and never fails (decision D-018). It writes no sentence of its own
 * about the mall: an answer is the lead sentence it was given, fixed phrases in the visitor's
 * language, and the words of the sources themselves, each followed by its number. Passages are
 * quoted (the sentences that share most words with the question); a list of results is listed.
 */
class TemplateAnswerGenerator implements AnswerGenerator
{
    /**
     * A passage up to this many characters is quoted whole.
     */
    private const QUOTE_WHOLE = 320;

    private const QUOTE_SENTENCES = 3;

    /**
     * A second passage is quoted too when it scored at least this share of the best one.
     */
    private const SECOND_PASSAGE = 0.9;

    public function __construct(private TextAnalyzer $analyzer) {}

    public function generate(GenerationRequestData $request): GeneratedAnswerData
    {
        if ($request->sources === []) {
            return new GeneratedAnswerData('', [], false, GeneratedAnswerData::TEMPLATE);
        }

        return $request->shape === GenerationRequestData::LISTING ? $this->listing($request) : $this->passages($request);
    }

    private function listing(GenerationRequestData $request): GeneratedAnswerData
    {
        $lines = $request->lead === null ? [] : [$request->lead];

        foreach ($request->sources as $source) {
            $lines[] = '• '.$source->text.' ['.$source->number.']';
        }

        return new GeneratedAnswerData(implode("\n", $lines), $request->sourceNumbers(), true, GeneratedAnswerData::TEMPLATE);
    }

    private function passages(GenerationRequestData $request): GeneratedAnswerData
    {
        $best = $request->sources[0];
        $quoted = [$best];
        $second = $request->sources[1] ?? null;

        if ($second !== null && $best->score !== null && $second->score !== null && $second->score >= self::SECOND_PASSAGE * $best->score) {
            $quoted[] = $second;
        }

        $lines = [];

        if ($request->uncertain) {
            $lines[] = __('I am not sure this answers your question. The closest information I have:', [], $request->language);
        } elseif ($request->lead !== null) {
            $lines[] = $request->lead;
        }

        foreach ($quoted as $source) {
            $lines[] = $this->excerpt($request->question, $source->text).' ['.$source->number.']';
        }

        return new GeneratedAnswerData(
            implode("\n", $lines),
            array_map(fn (SourceSnippetData $source): int => $source->number, $quoted),
            true,
            GeneratedAnswerData::TEMPLATE,
        );
    }

    /**
     * The part of a passage that answers: all of a short passage, otherwise the sentences that
     * share most words with the question, in their original order.
     */
    public function excerpt(string $question, string $text): string
    {
        if (mb_strlen($text) <= self::QUOTE_WHOLE) {
            return $text;
        }

        $sentences = $this->analyzer->sentences($text);
        $terms = array_fill_keys($this->analyzer->terms($question), true);
        $scores = [];

        foreach ($sentences as $index => $sentence) {
            $scores[$index] = count(array_intersect_key($this->analyzer->termCounts($sentence), $terms));
        }

        if (max($scores) === 0) {
            return implode(' ', array_slice($sentences, 0, 2));
        }

        arsort($scores);
        $chosen = array_slice(array_keys(array_filter($scores)), 0, self::QUOTE_SENTENCES);
        sort($chosen);

        return implode(' ', array_map(fn (int $index): string => $sentences[$index], $chosen));
    }
}
