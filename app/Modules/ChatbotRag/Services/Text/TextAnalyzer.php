<?php

namespace App\Modules\ChatbotRag\Services\Text;

use Normalizer;

/**
 * Turns text into the words retrieval compares: lower case, Bangla digits as ASCII, common words
 * removed, English words reduced to a rough stem. Deterministic and free of the framework, so the
 * same question always produces the same terms. Bangla words are compared whole; only English is
 * stemmed.
 */
class TextAnalyzer
{
    /**
     * Words that say nothing about what is asked.
     */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'am', 'to', 'of', 'in', 'on', 'at', 'for', 'with',
        'and', 'or', 'but', 'do', 'does', 'did', 'can', 'could', 'i', 'me', 'my', 'we', 'our', 'us', 'you', 'your',
        'it', 'its', 'this', 'that', 'these', 'those', 'there', 'here', 'what', 'which', 'who', 'whom', 'where',
        'when', 'why', 'how', 'any', 'some', 'have', 'has', 'had', 'will', 'would', 'should', 'shall', 'may',
        'might', 'please', 'tell', 'about', 'from', 'by', 'as', 'if', 'so', 'than', 'then', 'into', 'also', 'get',
        'got', 'need', 'want', 'like', 'know', 'find', 'show', 'give', 'let', 'hi', 'hello', 'hey', 'thanks',
        'thank', 'much', 'many', 'more', 'most', 'very', 'just', 'not', 'no', 'yes', 'ok', 'okay', 's', 't', 'im',
        'near', 'nearest', 'nearby', 'closest', 'around', 'mall', 'inside', 'looking', 'look', 'anything',
        'something', 'one', 'they', 'them', 'their', 'he', 'she', 'his', 'her', 'up', 'out', 'all',
        'কি', 'কী', 'কোথায়', 'কোথা', 'আছে', 'আছেন', 'এর', 'এই', 'ওই', 'সেই', 'একটি', 'একটা', 'আমি', 'আমার',
        'আমাকে', 'আমরা', 'আপনি', 'আপনার', 'তুমি', 'করে', 'করা', 'করতে', 'হবে', 'হয়', 'জন্য', 'থেকে', 'এবং', 'ও',
        'বা', 'না', 'কোন', 'কোনো', 'কখন', 'কত', 'কিভাবে', 'কীভাবে', 'যায়', 'যাবে', 'পাওয়া', 'পাব', 'পাবো', 'চাই',
        'দয়া', 'বলুন', 'টি', 'টা', 'কে', 'তে', 'এ', 'মল', 'মলে', 'শপিং',
    ];

    private const BANGLA_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    /** @var array<string, true>|null */
    private ?array $stopWords = null;

    /**
     * Lower case, Bangla digits as ASCII digits, one Unicode form, single spaces.
     */
    public function normalise(string $text): string
    {
        $text = strtr($text, self::BANGLA_DIGITS);

        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }

        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    /**
     * Every word of the text in order, lower case. Letters with their combining marks stay together,
     * which keeps Bangla words whole. A hyphenated word gives its parts and, for the text of a
     * document, also the parts written as one word, so "Wi-Fi" is found by "wifi" and by "wi-fi".
     *
     * @param  bool  $joinCompounds  False for a question: its hyphenated words are only split, so a joined form the visitor never typed is not counted as a word the answer must contain
     * @return list<string>
     */
    public function tokens(string $text, bool $joinCompounds = true): array
    {
        preg_match_all('/[\p{L}\p{M}\p{N}]+(?:-[\p{L}\p{M}\p{N}]+)*/u', $this->normalise($text), $matches);
        $tokens = [];

        foreach ($matches[0] as $word) {
            $parts = explode('-', $word);
            array_push($tokens, ...$parts);

            if ($joinCompounds && count($parts) > 1 && count($parts) <= 3) {
                $tokens[] = implode('', $parts);
            }
        }

        return $tokens;
    }

    /**
     * The words of a question that carry meaning, each once, in the order they first appear; not
     * stemmed.
     *
     * @return list<string>
     */
    public function contentWords(string $text): array
    {
        $words = [];

        foreach ($this->tokens($text, joinCompounds: false) as $token) {
            if (! $this->isStopWord($token)) {
                $words[$token] = true;
            }
        }

        return array_map('strval', array_keys($words));
    }

    /**
     * The stemmed content words of the text, each once.
     *
     * @return list<string>
     */
    public function terms(string $text): array
    {
        return array_values(array_unique(array_map($this->stem(...), $this->contentWords($text))));
    }

    /**
     * How often each stemmed content word occurs.
     *
     * @return array<string, int>
     */
    public function termCounts(string $text): array
    {
        $counts = [];

        foreach ($this->tokens($text) as $token) {
            if (! $this->isStopWord($token)) {
                $stem = $this->stem($token);
                $counts[$stem] = ($counts[$stem] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function isStopWord(string $token): bool
    {
        $this->stopWords ??= array_fill_keys(array_map($this->normalise(...), self::STOP_WORDS), true);

        return isset($this->stopWords[$token]);
    }

    /**
     * A rough English stem: plural, -ing and -ed endings and a final e are dropped, so that
     * "opening", "opens" and "open" compare equal. Words in other scripts are returned unchanged.
     */
    public function stem(string $word): string
    {
        if (preg_match('/^[a-z]+$/', $word) !== 1 || strlen($word) <= 3) {
            return $word;
        }

        if (str_ends_with($word, 'ies') && strlen($word) > 4) {
            $word = substr($word, 0, -3).'y';
        } elseif (str_ends_with($word, 'sses')) {
            $word = substr($word, 0, -2);
        } elseif (preg_match('/(ch|sh|x|z)es$/', $word) === 1) {
            $word = substr($word, 0, -2);
        } elseif (str_ends_with($word, 's') && ! str_ends_with($word, 'ss') && ! str_ends_with($word, 'us') && ! str_ends_with($word, 'is')) {
            $word = substr($word, 0, -1);
        }

        if (str_ends_with($word, 'ing') && strlen($word) > 5) {
            $word = substr($word, 0, -3);
        } elseif (str_ends_with($word, 'ed') && strlen($word) > 4) {
            $word = substr($word, 0, -2);
        }

        if (str_ends_with($word, 'e') && strlen($word) > 4) {
            $word = substr($word, 0, -1);
        }

        // "shopping" and "shop", "running" and "run": a doubled final consonant left by -ing or -ed.
        if (strlen($word) > 3 && preg_match('/([b-df-hj-np-tv-z])\1$/', $word) === 1 && ! preg_match('/(ll|ss|ff|zz)$/', $word)) {
            $word = substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * The sentences of a text; a line break always ends one. Understands the Bangla full stop.
     *
     * @return list<string>
     */
    public function sentences(string $text): array
    {
        $sentences = [];

        foreach (preg_split('/\n+/u', $text) ?: [] as $line) {
            foreach (preg_split('/(?<=[.!?।])\s+/u', trim($line)) ?: [] as $sentence) {
                if (trim($sentence) !== '') {
                    $sentences[] = trim($sentence);
                }
            }
        }

        return $sentences;
    }
}
