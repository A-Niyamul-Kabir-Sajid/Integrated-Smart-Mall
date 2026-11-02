<?php

namespace Tests\Unit\Core;

use App\Core\Services\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedNumbers(): array
    {
        return [
            'local mobile' => ['01712345678', '+8801712345678'],
            'local mobile with spaces' => ['017 1234 5678', '+8801712345678'],
            'country code without plus' => ['8801912345678', '+8801912345678'],
            'international with plus' => ['+880 1812-345678', '+8801812345678'],
            'international with 00 prefix' => ['00441632960123', '+441632960123'],
            'foreign number with plus' => ['+1 (415) 555-0100', '+14155550100'],
        ];
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function rejectedNumbers(): array
    {
        return [
            'null' => [null],
            'empty' => ['   '],
            'letters' => ['01712abc678'],
            'local number of unknown country' => ['4155550100'],
            'invalid bangladeshi operator digit' => ['01212345678'],
            'too short' => ['+12345'],
            'too long' => ['+1234567890123456'],
            'plus in the middle' => ['017+12345678'],
        ];
    }

    #[DataProvider('acceptedNumbers')]
    public function test_normalizes_accepted_numbers_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PhoneNumberNormalizer)->normalize($input));
    }

    #[DataProvider('rejectedNumbers')]
    public function test_rejects_numbers_it_cannot_resolve_unambiguously(?string $input): void
    {
        $this->assertNull((new PhoneNumberNormalizer)->normalize($input));
    }
}
