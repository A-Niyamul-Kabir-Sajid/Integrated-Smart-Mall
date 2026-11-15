<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

/**
 * Says how well a word of a question matches the words of a chunk: exactly, nearly (a spelling
 * mistake in an English word, an inflected Bangla word), or not at all.
 */
class TermMatcher
{
    public const EXACT = 1.0;

    public const NEAR = 0.8;

    /**
     * @param  string  $word  The word as the visitor typed it, lower case
     * @param  string  $stem  Its stem
     * @param  array<string, mixed>  $chunkTerms  The chunk's stemmed terms as keys
     * @param  array<string, mixed>  $chunkWords  The chunk's words as written, as keys
     */
    public function match(string $word, string $stem, array $chunkTerms, array $chunkWords): float
    {
        if (isset($chunkTerms[$stem]) || isset($chunkWords[$word])) {
            return self::EXACT;
        }

        // A spelling mistake is compared with the words as written: "parkng" is one letter from
        // "parking" but two from its stem.
        foreach ($chunkWords as $candidate => $_) {
            if ($this->isNear($word, (string) $candidate)) {
                return self::NEAR;
            }
        }

        return 0.0;
    }

    /**
     * English words of four letters or more may differ by one edit (two from eight letters) when
     * they start alike; words in other scripts match when one starts with the other.
     */
    public function isNear(string $term, string $candidate): bool
    {
        if (preg_match('/^[a-z]+$/', $term) === 1 && preg_match('/^[a-z]+$/', $candidate) === 1) {
            $length = strlen($term);

            if ($length < 4 || $term[0] !== $candidate[0] || abs($length - strlen($candidate)) > 2) {
                return false;
            }

            return levenshtein($term, $candidate) <= ($length >= 8 ? 2 : 1);
        }

        if (preg_match('/^[\p{N}a-z]+$/u', $term) === 1 || preg_match('/^[\p{N}a-z]+$/u', $candidate) === 1) {
            return false;
        }

        $shorter = mb_strlen($term) <= mb_strlen($candidate) ? $term : $candidate;
        $longer = $shorter === $term ? $candidate : $term;

        return mb_strlen($shorter) >= 3 && str_starts_with($longer, $shorter);
    }
}
