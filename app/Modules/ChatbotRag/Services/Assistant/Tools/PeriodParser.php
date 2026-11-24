<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use Carbon\CarbonImmutable;

/**
 * Reads the period an administrator's question is about ("last month", "this week", "in October",
 * "last 14 days", "from 2026-10-01 to 2026-10-15") as whole days in the mall's time zone, the same
 * way the dashboards count days. A question that names no period is about the dashboards' default
 * period, and the answer says which days that was.
 */
class PeriodParser
{
    private const MONTHS = ['january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12];

    /**
     * @param  string  $text  The question, lower case
     * @return array{period: ReportPeriod, named: bool} named is false when the question named no period
     */
    public function parse(string $text, Mall $mall): array
    {
        $today = CarbonImmutable::now($mall->timezone)->startOfDay();
        $range = $this->range($text, $today);

        return [
            'period' => ReportPeriod::fromInput($mall, $range[0] ?? null, $range[1] ?? null),
            'named' => $range !== null,
        ];
    }

    /**
     * The month a question about a monthly report means, as year-month: a named month, "last month"
     * or a written year-month; otherwise the current month.
     */
    public function month(string $text, Mall $mall): string
    {
        $today = CarbonImmutable::now($mall->timezone)->startOfDay();

        if (preg_match('/\b(\d{4})-(0[1-9]|1[0-2])\b/', $text, $match) === 1) {
            return $match[1].'-'.$match[2];
        }

        if (preg_match('/\b(last|previous) month\b/', $text) === 1) {
            return $today->startOfMonth()->subMonth()->format('Y-m');
        }

        return ($this->namedMonth($text, $today) ?? $today)->format('Y-m');
    }

    /**
     * @return array{0: string, 1: string}|null First and last day as year-month-day
     */
    private function range(string $text, CarbonImmutable $today): ?array
    {
        $day = fn (CarbonImmutable $date): string => $date->format('Y-m-d');

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b\s*(?:to|and|until|till|through|-|–)\s*\b(\d{4}-\d{2}-\d{2})\b/', $text, $match) === 1) {
            return [$match[1], $match[2]];
        }

        if (preg_match('/\b(?:on\s+)?(\d{4}-\d{2}-\d{2})\b/', $text, $match) === 1) {
            return [$match[1], $match[1]];
        }

        if (preg_match('/\b(\d{4})-(0[1-9]|1[0-2])\b/', $text, $match) === 1) {
            return $this->monthRange($today->setDate((int) $match[1], (int) $match[2], 1), $today);
        }

        if (preg_match('/\b(?:last|past|previous)\s+(\d{1,3})\s+(day|days|week|weeks)\b/', $text, $match) === 1) {
            $days = max(1, (int) $match[1] * (str_starts_with($match[2], 'week') ? 7 : 1));

            return [$day($today->subDays($days - 1)), $day($today)];
        }

        return match (true) {
            preg_match('/\btoday\b/', $text) === 1 => [$day($today), $day($today)],
            preg_match('/\byesterday\b/', $text) === 1 => [$day($today->subDay()), $day($today->subDay())],
            preg_match('/\bthis week\b/', $text) === 1 => [$day($today->startOfWeek(CarbonImmutable::MONDAY)), $day($today)],
            preg_match('/\b(last|previous) week\b/', $text) === 1 => [$day($today->startOfWeek(CarbonImmutable::MONDAY)->subWeek()), $day($today->startOfWeek(CarbonImmutable::MONDAY)->subDay())],
            preg_match('/\bthis month\b/', $text) === 1 => [$day($today->startOfMonth()), $day($today)],
            preg_match('/\b(last|previous) month\b/', $text) === 1 => $this->monthRange($today->startOfMonth()->subMonth(), $today),
            preg_match('/\bthis year\b/', $text) === 1 => [$day($today->startOfYear()), $day($today)],
            default => ($month = $this->namedMonth($text, $today)) === null ? null : $this->monthRange($month, $today),
        };
    }

    /**
     * The first day of a month named in the text ("october", "october 2025"). Without a year it is
     * the latest such month that has started.
     */
    private function namedMonth(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        if (preg_match('/\b('.implode('|', array_keys(self::MONTHS)).')\b(?:\s+(\d{4}))?/', $text, $match) !== 1) {
            return null;
        }

        $month = self::MONTHS[$match[1]];
        $year = isset($match[2]) ? (int) $match[2] : ($month > $today->month ? $today->year - 1 : $today->year);

        return $today->setDate($year, $month, 1);
    }

    /**
     * The whole month, or up to today when the month is not over.
     *
     * @return array{0: string, 1: string}
     */
    private function monthRange(CarbonImmutable $first, CarbonImmutable $today): array
    {
        $last = $first->endOfMonth()->startOfDay();

        return [$first->format('Y-m-d'), ($last->gt($today) && $first->lte($today) ? $today : $last)->format('Y-m-d')];
    }
}
