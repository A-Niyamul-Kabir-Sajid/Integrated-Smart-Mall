<?php

namespace Tests\Unit\ChatbotRag;

use App\Modules\ChatbotRag\Services\Retrieval\QueryExpander;
use App\Modules\ChatbotRag\Services\Retrieval\TermMatcher;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RetrievalMatchingTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function nearAndFarWords(): array
    {
        return [
            'one letter missing' => ['parkng', 'parking', true],
            'one letter wrong' => ['cofee', 'coffee', true],
            'two letters wrong in a long word' => ['accesible', 'accessible', true],
            'different word' => ['park', 'bank', false],
            'short word' => ['atm', 'arm', false],
            'bangla word with an ending' => ['দোকানে', 'দোকান', true],
            'different bangla words' => ['দোকান', 'দাম', false],
            'number' => ['100', '1000', false],
        ];
    }

    #[DataProvider('nearAndFarWords')]
    public function test_a_misspelt_or_inflected_word_is_near_and_another_word_is_not(string $term, string $candidate, bool $near): void
    {
        $this->assertSame($near, (new TermMatcher)->isNear($term, $candidate));
    }

    public function test_a_word_matches_exactly_by_its_stem_and_nearly_by_its_spelling(): void
    {
        $matcher = new TermMatcher;
        $chunkTerms = ['park' => 2, 'cost' => 1];
        $chunkWords = ['parking' => true, 'costs' => true];

        $this->assertSame(TermMatcher::EXACT, $matcher->match('parked', 'park', $chunkTerms, $chunkWords));
        $this->assertSame(TermMatcher::NEAR, $matcher->match('parkng', 'parkng', $chunkTerms, $chunkWords));
        $this->assertSame(0.0, $matcher->match('helicopter', 'helicopter', $chunkTerms, $chunkWords));
    }

    public function test_words_with_the_same_meaning_are_alternatives_across_languages(): void
    {
        $analyzer = new TextAnalyzer;
        $expander = new QueryExpander($analyzer);

        $this->assertContains('toilet', $expander->alternatives('washroom'));
        $this->assertContains('prayer', $expander->alternatives($analyzer->normalise('নামাজ')));
        $this->assertNotContains('washroom', $expander->alternatives('washroom'));
        $this->assertSame([], $expander->alternatives('umbrella'));
    }
}
