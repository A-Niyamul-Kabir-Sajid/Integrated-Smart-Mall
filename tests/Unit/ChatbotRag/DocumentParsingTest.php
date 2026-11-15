<?php

namespace Tests\Unit\ChatbotRag;

use App\Modules\ChatbotRag\Services\Knowledge\Chunker;
use App\Modules\ChatbotRag\Services\Knowledge\DocumentParser;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use PHPUnit\Framework\TestCase;

class DocumentParsingTest extends TestCase
{
    public function test_headings_start_sections_and_text_before_the_first_heading_has_none(): void
    {
        $parsed = (new DocumentParser)->parse("Welcome to the mall.\n\n# Parking\nParking is in the basement.\nIt costs BDT 30.\n\n## Pets ##\nPets are not allowed.");

        $this->assertSame([
            ['heading' => null, 'body' => 'Welcome to the mall.'],
            ['heading' => 'Parking', 'body' => "Parking is in the basement.\nIt costs BDT 30."],
            ['heading' => 'Pets', 'body' => 'Pets are not allowed.'],
        ], $parsed['sections']);
    }

    public function test_a_question_becomes_the_heading_of_its_answer(): void
    {
        $parsed = (new DocumentParser)->parse("Q: Is there free Wi-Fi?\nA: Yes, the network is NCM-Guest.\n\nপ্রশ্ন: পার্কিং ফি কত?\nউত্তর: ৩০ টাকা।");

        $this->assertSame([
            ['heading' => 'Is there free Wi-Fi?', 'body' => 'Yes, the network is NCM-Guest.'],
            ['heading' => 'পার্কিং ফি কত?', 'body' => '৩০ টাকা।'],
        ], $parsed['sections']);
    }

    public function test_markup_control_characters_and_invisible_marks_are_removed(): void
    {
        $hidden = "\u{200B}\u{202E}";
        $text = (new DocumentParser)->clean("<b>Opening</b> hours\r\n\r\n\r\n\r\nDaily{$hidden} 10:00\x07 to 22:00   \t ");

        $this->assertSame("Opening hours\n\nDaily 10:00 to 22:00", $text);
    }

    public function test_text_with_only_headings_or_blank_lines_has_no_sections(): void
    {
        $this->assertSame([], (new DocumentParser)->parse("# Heading only\n\n   \n")['sections']);
    }

    public function test_a_section_that_fits_stays_one_chunk_with_its_heading(): void
    {
        $chunks = (new Chunker(new TextAnalyzer))->chunk([['heading' => 'Parking', 'body' => 'Parking is in the basement.']], 700, 1000);

        $this->assertSame([['heading' => 'Parking', 'text' => 'Parking is in the basement.']], $chunks);
    }

    public function test_a_long_section_is_cut_at_sentence_ends_with_the_last_sentence_repeated(): void
    {
        $body = 'Sentence one is here. Sentence two is here. Sentence three is here. Sentence four is here. Sentence five is here.';

        $chunks = (new Chunker(new TextAnalyzer))->chunk([['heading' => 'Rules', 'body' => $body]], 50, 80);

        $this->assertSame([
            ['heading' => 'Rules', 'text' => "Sentence one is here.\nSentence two is here."],
            ['heading' => 'Rules', 'text' => "Sentence two is here.\nSentence three is here."],
            ['heading' => 'Rules', 'text' => "Sentence three is here.\nSentence four is here."],
            ['heading' => 'Rules', 'text' => "Sentence four is here.\nSentence five is here."],
        ], $chunks);
    }

    public function test_no_chunk_exceeds_the_maximum_even_without_sentence_ends(): void
    {
        $chunks = (new Chunker(new TextAnalyzer))->chunk([['heading' => null, 'body' => str_repeat('x', 250)]], 60, 100);

        $this->assertSame([100, 100, 50], array_map(fn (array $chunk): int => mb_strlen($chunk['text']), $chunks));
    }
}
