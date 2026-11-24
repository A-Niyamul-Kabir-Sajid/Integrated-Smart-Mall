<?php

namespace App\Modules\ChatbotRag\Services\Knowledge;

use Illuminate\Support\Str;

/**
 * Reads the ingestion format of a knowledge document: plain text in which a line starting with "#"
 * is a section heading, and "Q:" / "A:" lines (or "প্রশ্ন:" / "উত্তর:") are a question and its answer.
 * Markup, control characters and invisible direction marks are removed, so what is stored is exactly
 * what an administrator can read on the screen.
 */
class DocumentParser
{
    public const HEADING_MAX = 200;

    /**
     * @return array{text: string, sections: list<array{heading: string|null, body: string}>}
     */
    public function parse(string $raw): array
    {
        $text = $this->clean($raw);
        $sections = [];
        $heading = null;
        $lines = [];

        $close = function () use (&$sections, &$heading, &$lines): void {
            $body = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));

            if ($body !== '') {
                $sections[] = ['heading' => $heading, 'body' => $body];
            }

            $lines = [];
        };

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^#{1,6}\s+(.+?)[\s#]*$/u', $line, $match) === 1) {
                $close();
                $heading = Str::limit(trim($match[1]), self::HEADING_MAX, '');
            } elseif (preg_match('/^(?:Q|Question|প্রশ্ন)\s*:\s*(.+)$/iu', $line, $match) === 1) {
                $close();
                $heading = Str::limit(trim($match[1]), self::HEADING_MAX, '');
            } elseif (preg_match('/^(?:A|Answer|উত্তর)\s*:\s*(.*)$/iu', $line, $match) === 1) {
                $lines[] = trim($match[1]);
            } else {
                $lines[] = $line;
            }
        }

        $close();

        return ['text' => $text, 'sections' => $sections];
    }

    /**
     * The text as it is stored: no markup, no control or invisible formatting characters, Unix line
     * ends, no trailing spaces, at most one empty line in a row.
     */
    public function clean(string $raw): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", strip_tags($raw));
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        // Zero-width and direction marks can hide text from a reader. The joiners Bangla needs stay.
        $text = (string) preg_replace('/[\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = implode("\n", array_map('trim', explode("\n", $text)));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
