<?php

namespace Tests\Unit\ChatbotRag;

use App\Modules\ChatbotRag\Services\Text\LanguageDetector;
use App\Modules\ChatbotRag\Services\Text\PersonalDataMasker;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextAnalyzerTest extends TestCase
{
    public function test_tokens_are_lower_case_words_with_hyphenated_words_also_joined(): void
    {
        $tokens = (new TextAnalyzer)->tokens('Free Wi-Fi at Unit G-01!');

        $this->assertSame(['free', 'wi', 'fi', 'wifi', 'at', 'unit', 'g', '01', 'g01'], $tokens);
    }

    public function test_bangla_words_stay_whole_and_bangla_digits_become_ascii(): void
    {
        $tokens = (new TextAnalyzer)->tokens('পার্কিং ফি ৩০ টাকা');

        $this->assertSame(['পার্কিং', 'ফি', '30', 'টাকা'], $tokens);
    }

    public function test_content_words_drop_common_english_and_bangla_words(): void
    {
        $analyzer = new TextAnalyzer;

        $this->assertSame(['parking', 'fee'], $analyzer->contentWords('What is the parking fee at the mall?'));
        $this->assertSame(['পার্কিং'], $analyzer->contentWords('পার্কিং কোথায় আছে?'));
    }

    /**
     * @return array<string, array{0: list<string>}>
     */
    public static function wordsWithOneStem(): array
    {
        return [
            'open' => [['open', 'opens', 'opening', 'opened']],
            'drink' => [['drink', 'drinks']],
            'price' => [['price', 'prices', 'pricing']],
            'shop' => [['shop', 'shops', 'shopping']],
            'close' => [['close', 'closes', 'closing', 'closed']],
            'battery' => [['battery', 'batteries']],
        ];
    }

    /**
     * @param  list<string>  $words
     */
    #[DataProvider('wordsWithOneStem')]
    public function test_inflected_english_words_share_one_stem(array $words): void
    {
        $stems = array_unique(array_map((new TextAnalyzer)->stem(...), $words));

        $this->assertCount(1, $stems, 'Stems: '.implode(', ', $stems));
    }

    public function test_short_words_and_other_scripts_are_not_stemmed(): void
    {
        $analyzer = new TextAnalyzer;

        $this->assertSame('bus', $analyzer->stem('bus'));
        $this->assertSame('দোকানে', $analyzer->stem('দোকানে'));
    }

    public function test_sentences_end_at_full_stops_line_breaks_and_the_bangla_full_stop(): void
    {
        $sentences = (new TextAnalyzer)->sentences("Parking is free. Open daily!\nমল খোলা। স্বাগতম");

        $this->assertSame(['Parking is free.', 'Open daily!', 'মল খোলা।', 'স্বাগতম'], $sentences);
    }

    public function test_term_counts_count_stems(): void
    {
        $this->assertSame(['drink' => 2, 'cold' => 1], (new TextAnalyzer)->termCounts('Drinks and a cold drink'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function questionsAndLanguages(): array
    {
        return [
            'english' => ['Where is the prayer room?', 'en'],
            'bangla' => ['নামাজের ঘর কোথায়?', 'bn'],
            'bangla with a shop name' => ['Cafe Brew কখন খোলে এবং কোথায় আছে?', 'bn'],
            'english with one bangla word' => ['Where can I buy a good panjabi for ঈদ at this mall today?', 'en'],
        ];
    }

    #[DataProvider('questionsAndLanguages')]
    public function test_language_follows_the_script_of_the_question(string $question, string $language): void
    {
        $this->assertSame($language, (new LanguageDetector)->detect($question));
    }

    public function test_text_without_letters_uses_the_fallback_language(): void
    {
        $detector = new LanguageDetector;

        $this->assertSame('bn', $detector->detect('500?', 'bn'));
        $this->assertSame('en', $detector->detect('500?', 'fr'));
    }

    public function test_email_addresses_and_long_numbers_are_masked(): void
    {
        $masked = (new PersonalDataMasker)->mask('Call me on +880 1712-345678 or mail rahim.k@example.com about order 12, under 1500 taka');

        $this->assertSame('Call me on [number removed] or mail [email removed] about order 12, under 1500 taka', $masked);
    }

    public function test_bangla_phone_numbers_are_masked(): void
    {
        $this->assertSame('আমার নম্বর [number removed]', (new PersonalDataMasker)->mask('আমার নম্বর ০১৭১২৩৪৫৬৭৮'));
    }
}
