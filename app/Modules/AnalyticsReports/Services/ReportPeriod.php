<?php

namespace App\Modules\AnalyticsReports\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Mall;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * A range of whole days in the mall's time zone, both ends included. Between startsAt() and endsAt()
 * the range is half-open, so a day never belongs to two ranges.
 */
final class ReportPeriod
{
    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $timezone,
    ) {}

    public static function fromInput(Mall $mall, ?string $from, ?string $to): self
    {
        $timezone = $mall->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $end = filled($to) ? self::parse($to, $timezone) : $today;
        $start = filled($from) ? self::parse($from, $timezone) : $end->subDays((int) config('analytics.default_report_days', 30) - 1);

        if ($end->lt($start)) {
            throw new BusinessRuleViolation('report.invalid_period', __('The end date must not be before the start date.'));
        }

        if ($start->diffInDays($end) + 1 > (int) config('analytics.max_report_days', 366)) {
            throw new BusinessRuleViolation('report.period_too_long', __('Choose a period of at most :days days.', ['days' => (int) config('analytics.max_report_days', 366)]));
        }

        return new self($start, $end, $timezone);
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->from->startOfDay()->utc();
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->to->addDay()->startOfDay()->utc();
    }

    public function dayCount(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    private static function parse(string $value, string $timezone): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        } catch (Throwable) {
            $date = false;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw new BusinessRuleViolation('report.invalid_date', __('Enter dates as year-month-day.'));
        }

        return $date;
    }
}
