<?php

namespace App\Core\Services;

/**
 * Normalizes phone numbers to E.164 (for example +8801712345678) so they can be
 * stored once and compared for uniqueness.
 *
 * Accepted input: international numbers starting with "+" or "00", Bangladeshi
 * mobile numbers in local form (01XXXXXXXXX) and Bangladeshi numbers written
 * with the country code but without "+" (8801XXXXXXXXX). Spaces, dashes, dots
 * and brackets are ignored. Anything else is rejected rather than guessed.
 */
final class PhoneNumberNormalizer
{
    private const BANGLADESH_COUNTRY_CODE = '880';

    public function normalize(?string $input): ?string
    {
        $trimmed = trim((string) $input);

        if ($trimmed === '' || preg_match('/^\+?[\d\s\-().]+$/', $trimmed) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);

        if (! str_starts_with($trimmed, '+')) {
            $digits = match (true) {
                str_starts_with($digits, '00') => substr($digits, 2),
                preg_match('/^01[3-9]\d{8}$/', $digits) === 1 => self::BANGLADESH_COUNTRY_CODE.substr($digits, 1),
                preg_match('/^8801[3-9]\d{8}$/', $digits) === 1 => $digits,
                default => '',
            };
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }
}
