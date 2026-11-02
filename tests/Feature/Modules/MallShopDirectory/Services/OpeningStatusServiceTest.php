<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use App\Modules\MallShopDirectory\Services\OpeningStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpeningStatusServiceTest extends TestCase
{
    /**
     * @param  list<array{0: int, 1: int, 2: string, 3: string, 4: bool}>  $rows
     * @return Collection<int, ShopOpeningHour>
     */
    private function hours(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row): ShopOpeningHour => (new ShopOpeningHour)->forceFill([
            'weekday' => $row[0], 'interval_no' => $row[1], 'opens_at' => $row[2], 'closes_at' => $row[3], 'closes_next_day' => $row[4],
        ]));
    }

    /**
     * Moments are given in Asia/Dhaka local time; 2026-11-02 is a Monday.
     *
     * @return array<string, array{0: list<array{0: int, 1: int, 2: string, 3: string, 4: bool}>, 1: string, 2: bool}>
     */
    public static function moments(): array
    {
        $daily = [[1, 1, '10:00', '21:00', false]];
        $split = [[1, 1, '11:00', '15:00', false], [1, 2, '17:00', '23:30', false]];
        $lateNight = [[1, 1, '18:00', '02:00', true]];
        $allDay = [[1, 1, '00:00', '00:00', true]];
        $sundayNight = [[7, 1, '22:00', '03:00', true]];

        return [
            'inside a daily interval' => [$daily, '2026-11-02 12:00', true],
            'at closing time is closed' => [$daily, '2026-11-02 21:00', false],
            'before opening' => [$daily, '2026-11-02 09:59', false],
            'gap between split intervals' => [$split, '2026-11-02 16:00', false],
            'second split interval' => [$split, '2026-11-02 18:00', true],
            'overnight, before midnight' => [$lateNight, '2026-11-02 23:00', true],
            'overnight, after midnight next day' => [$lateNight, '2026-11-03 01:30', true],
            'overnight, after it closed' => [$lateNight, '2026-11-03 02:00', false],
            'twenty-four hours' => [$allDay, '2026-11-02 04:00', true],
            'twenty-four hours ends at the next midnight' => [$allDay, '2026-11-03 00:30', false],
            'twenty-four hours from 03:00 reaches the next morning' => [[[1, 1, '03:00', '03:00', true]], '2026-11-03 02:59', true],
            'sunday night wraps to monday' => [$sundayNight, '2026-11-02 02:00', true],
            'no interval that day means closed' => [$daily, '2026-11-03 12:00', false],
        ];
    }

    /**
     * @param  list<array{0: int, 1: int, 2: string, 3: string, 4: bool}>  $rows
     */
    #[DataProvider('moments')]
    public function test_decides_whether_a_shop_is_open_in_the_mall_timezone(array $rows, string $localMoment, bool $expected): void
    {
        $moment = CarbonImmutable::parse($localMoment, 'Asia/Dhaka')->utc();

        $this->assertSame($expected, (new OpeningStatusService)->isOpenAt($this->hours($rows), $moment, 'Asia/Dhaka'));
    }

    public function test_weekly_schedule_formats_split_overnight_and_all_day_intervals(): void
    {
        $schedule = (new OpeningStatusService)->weeklySchedule($this->hours([
            [1, 2, '17:00', '23:30', false], [1, 1, '11:00', '15:00', false],
            [5, 1, '18:00', '02:00', true],
            [6, 1, '00:00', '00:00', true],
        ]));

        $this->assertSame(['11:00 – 15:00', '17:00 – 23:30'], $schedule[1]);
        $this->assertSame(['18:00 – 02:00 (+1)'], $schedule[5]);
        $this->assertSame(['Open 24 hours'], $schedule[6]);
        $this->assertSame([], $schedule[2]);
    }
}
