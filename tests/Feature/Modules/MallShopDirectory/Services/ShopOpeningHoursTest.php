<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use App\Modules\MallShopDirectory\Services\ShopService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShopOpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: int, 1: int, 2: string, 3: string, 4: bool}>
     */
    private function storedHours(Shop $shop): array
    {
        return $shop->openingHours()->get()->map(fn (ShopOpeningHour $hour): array => [
            $hour->weekday, $hour->interval_no, substr($hour->opens_at, 0, 5), substr($hour->closes_at, 0, 5), $hour->closes_next_day,
        ])->all();
    }

    public function test_stores_split_overnight_and_all_day_intervals_numbered_per_day(): void
    {
        $shop = Shop::factory()->create();

        app(ShopService::class)->setOpeningHours($shop, [
            ['weekday' => 1, 'opens_at' => '17:00', 'closes_at' => '23:30'],
            ['weekday' => 1, 'opens_at' => '11:00', 'closes_at' => '15:00'],
            ['weekday' => 5, 'opens_at' => '18:00', 'closes_at' => '02:00', 'closes_next_day' => true],
            ['weekday' => 6, 'opens_at' => '03:00', 'closes_at' => '03:00', 'closes_next_day' => true],
        ]);

        $this->assertSame([
            [1, 1, '11:00', '15:00', false],
            [1, 2, '17:00', '23:30', false],
            [5, 1, '18:00', '02:00', true],
            [6, 1, '03:00', '03:00', true],
        ], $this->storedHours($shop));
        $this->assertSame(['intervals' => 4], AuditLog::query()->sole()->metadata);
    }

    public function test_replaces_the_previous_schedule(): void
    {
        $shop = Shop::factory()->create();
        $service = app(ShopService::class);
        $service->setOpeningHours($shop, [['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '20:00']]);

        $service->setOpeningHours($shop, [['weekday' => 2, 'opens_at' => '09:00', 'closes_at' => '18:00']]);

        $this->assertSame([[2, 1, '09:00', '18:00', false]], $this->storedHours($shop));
    }

    /**
     * @return array<string, array{0: list<array<string, mixed>>, 1: string}>
     */
    public static function invalidSchedules(): array
    {
        return [
            'same-day overlap' => [[
                ['weekday' => 3, 'opens_at' => '10:00', 'closes_at' => '15:00'],
                ['weekday' => 3, 'opens_at' => '14:00', 'closes_at' => '20:00'],
            ], 'opening_hour.overlap'],
            'overnight spill into next morning' => [[
                ['weekday' => 4, 'opens_at' => '20:00', 'closes_at' => '02:00', 'closes_next_day' => true],
                ['weekday' => 5, 'opens_at' => '01:00', 'closes_at' => '09:00'],
            ], 'opening_hour.overlap'],
            'sunday night wraps into monday' => [[
                ['weekday' => 7, 'opens_at' => '22:00', 'closes_at' => '04:00', 'closes_next_day' => true],
                ['weekday' => 1, 'opens_at' => '03:00', 'closes_at' => '10:00'],
            ], 'opening_hour.overlap'],
            'closes before it opens' => [[
                ['weekday' => 1, 'opens_at' => '18:00', 'closes_at' => '09:00'],
            ], 'opening_hour.closes_before_opening'],
            'weekday out of range' => [[
                ['weekday' => 8, 'opens_at' => '10:00', 'closes_at' => '18:00'],
            ], 'opening_hour.invalid_weekday'],
            'not a time' => [[
                ['weekday' => 1, 'opens_at' => '25:00', 'closes_at' => '18:00'],
            ], 'opening_hour.invalid_time'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $intervals
     */
    #[DataProvider('invalidSchedules')]
    public function test_rejects_invalid_schedules_and_keeps_the_old_one(array $intervals, string $rule): void
    {
        $shop = Shop::factory()->create();
        $service = app(ShopService::class);
        $service->setOpeningHours($shop, [['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '20:00']]);

        try {
            $service->setOpeningHours($shop, $intervals);
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }

        $this->assertSame([[1, 1, '10:00', '20:00', false]], $this->storedHours($shop));
    }
}
