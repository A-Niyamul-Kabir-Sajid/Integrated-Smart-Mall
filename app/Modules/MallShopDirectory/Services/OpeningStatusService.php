<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Evaluates weekly SHOP_OPENING_HOUR intervals in the mall timezone.
 *
 * Business rules: no interval on a weekday means closed; an overnight
 * interval has closes_next_day=true; a 24-hour interval uses equal opening
 * and closing times with closes_next_day=true. Holiday exceptions are not
 * part of the baseline and are never inferred.
 */
class OpeningStatusService
{
    /**
     * @param  Collection<int, ShopOpeningHour>  $hours
     */
    public function isOpenAt(Collection $hours, CarbonInterface $moment, string $timezone): bool
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($timezone);
        $minuteOfDay = $local->hour * 60 + $local->minute;
        $today = $local->isoWeekday();
        $yesterday = $today === 1 ? 7 : $today - 1;

        foreach ($hours as $interval) {
            $opens = $this->minutes($interval->opens_at);
            $closes = $this->minutes($interval->closes_at);

            if ($interval->weekday === $today) {
                if ($interval->closes_next_day && $minuteOfDay >= $opens) {
                    return true;
                }

                if (! $interval->closes_next_day && $minuteOfDay >= $opens && $minuteOfDay < $closes) {
                    return true;
                }
            }

            if ($interval->weekday === $yesterday && $interval->closes_next_day && $minuteOfDay < $closes) {
                return true;
            }
        }

        return false;
    }

    /**
     * Intervals for the given moment's local weekday, formatted for display.
     *
     * @param  Collection<int, ShopOpeningHour>  $hours
     * @return list<string>
     */
    public function intervalsForDay(Collection $hours, int $isoWeekday): array
    {
        return $hours
            ->where('weekday', $isoWeekday)
            ->sortBy('interval_no')
            ->map(fn (ShopOpeningHour $interval): string => $this->formatInterval($interval))
            ->values()
            ->all();
    }

    /**
     * Opening hours grouped by ISO weekday (1 = Monday) for a weekly table.
     *
     * @param  Collection<int, ShopOpeningHour>  $hours
     * @return array<int, list<string>>
     */
    public function weeklySchedule(Collection $hours): array
    {
        $schedule = [];

        foreach (range(1, 7) as $weekday) {
            $schedule[$weekday] = $this->intervalsForDay($hours, $weekday);
        }

        return $schedule;
    }

    private function formatInterval(ShopOpeningHour $interval): string
    {
        $opens = substr((string) $interval->opens_at, 0, 5);
        $closes = substr((string) $interval->closes_at, 0, 5);

        if ($interval->closes_next_day && $opens === $closes) {
            return __('Open 24 hours');
        }

        return $interval->closes_next_day
            ? $opens.' – '.$closes.' (+1)'
            : $opens.' – '.$closes;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
