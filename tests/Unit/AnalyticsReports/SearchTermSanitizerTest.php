<?php

namespace Tests\Unit\AnalyticsReports;

use App\Modules\AnalyticsReports\Services\CrawlerDetector;
use App\Modules\AnalyticsReports\Services\SearchTermSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchTermSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function normalised(): array
    {
        return [
            'case' => ['COFFEE', 'coffee'],
            'spaces around' => ['  coffee  ', 'coffee'],
            'inner spaces' => ["gift \t  card", 'gift card'],
            'newline' => ["tea\nbags", 'tea bags'],
            'unicode' => ['  CAFÉ  ', 'café'],
            'bengali' => ['চা', 'চা'],
            'short number' => ['iphone 15', 'iphone 15'],
        ];
    }

    #[DataProvider('normalised')]
    public function test_variants_of_one_term_become_one_term(?string $input, ?string $expected): void
    {
        $this->assertSame(['term' => $expected, 'masked' => false], (new SearchTermSanitizer)->sanitize($input));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function personal(): array
    {
        return [
            'email' => ['rina@example.test'],
            'email in words' => ['mail me at a@b.co please'],
            'phone' => ['01712345678'],
            'phone with country code and spaces' => ['+880 1712-345678'],
            'phone in brackets' => ['(017) 123 4567'],
            'barcode length number' => ['5901234123457'],
        ];
    }

    #[DataProvider('personal')]
    public function test_text_that_looks_personal_is_masked(string $input): void
    {
        $this->assertSame(['term' => null, 'masked' => true], (new SearchTermSanitizer)->sanitize($input));
    }

    public function test_empty_text_is_neither_a_term_nor_masked(): void
    {
        foreach ([null, '', '   ', "\n\t"] as $input) {
            $this->assertSame(['term' => null, 'masked' => false], (new SearchTermSanitizer)->sanitize($input));
        }
    }

    public function test_long_text_is_cut_at_the_configured_length(): void
    {
        $term = (new SearchTermSanitizer)->sanitize(str_repeat('a', 300))['term'];

        $this->assertSame(100, mb_strlen((string) $term));
    }

    public function test_browsers_pass_and_automated_clients_do_not(): void
    {
        $detector = new CrawlerDetector;

        $this->assertFalse($detector->isCrawler('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'));
        $this->assertFalse($detector->isCrawler('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1'));

        foreach ([null, '', 'Googlebot/2.1', 'curl/8.0', 'python-requests/2.31', 'Wget/1.21', 'Mozilla/5.0 (compatible; AhrefsBot/7.0)', 'Lighthouse'] as $agent) {
            $this->assertTrue($detector->isCrawler($agent), (string) $agent);
        }
    }
}
