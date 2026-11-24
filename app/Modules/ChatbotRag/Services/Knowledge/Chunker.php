<?php

namespace App\Modules\ChatbotRag\Services\Knowledge;

use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;

/**
 * Cuts the sections of a document into retrieval-sized chunks. A section that fits stays whole, so a
 * question keeps its answer; a longer one is cut at paragraph and sentence ends, and each chunk
 * repeats the last sentence of the one before so that a fact split across the cut is still found.
 * Every chunk keeps the heading of its section.
 */
class Chunker
{
    public function __construct(private TextAnalyzer $analyzer) {}

    /**
     * @param  list<array{heading: string|null, body: string}>  $sections
     * @param  int  $target  Size in characters a chunk grows to before a new one starts
     * @param  int  $max  Size in characters no chunk exceeds
     * @return list<array{heading: string|null, text: string}>
     */
    public function chunk(array $sections, int $target, int $max): array
    {
        $target = max(50, min($target, $max));
        $chunks = [];

        foreach ($sections as $section) {
            $pieces = mb_strlen($section['body']) <= $max ? [$section['body']] : $this->split($section['body'], $target, $max);

            foreach ($pieces as $piece) {
                $chunks[] = ['heading' => $section['heading'], 'text' => $piece];
            }
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function split(string $body, int $target, int $max): array
    {
        $chunks = [];
        $current = '';

        foreach ($this->units($body, $target, $max) as $unit) {
            $joined = $current === '' ? $unit : $current."\n".$unit;

            if ($current === '' || mb_strlen($joined) <= $target) {
                $current = $joined;

                continue;
            }

            $chunks[] = $current;
            $overlap = $this->lastSentence($current);
            $current = $overlap !== $current && mb_strlen($overlap) + mb_strlen($unit) + 1 <= $max ? $overlap."\n".$unit : $unit;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Paragraphs that fit the target, otherwise their sentences, otherwise hard cuts of a sentence
     * longer than the maximum.
     *
     * @return list<string>
     */
    private function units(string $body, int $target, int $max): array
    {
        $units = [];

        foreach (preg_split("/\n{2,}/", $body) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= $target) {
                $units[] = $paragraph;

                continue;
            }

            foreach ($this->analyzer->sentences($paragraph) as $sentence) {
                array_push($units, ...mb_str_split($sentence, $max));
            }
        }

        return $units;
    }

    private function lastSentence(string $chunk): string
    {
        $sentences = $this->analyzer->sentences($chunk);

        return $sentences === [] ? $chunk : $sentences[array_key_last($sentences)];
    }
}
