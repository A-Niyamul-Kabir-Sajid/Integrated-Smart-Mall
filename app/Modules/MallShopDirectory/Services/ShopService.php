<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Core\Services\PhoneNumberNormalizer;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Events\ShopStatusChanged;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates shops and moves them through their lifecycle. Other modules change
 * shop records only through this service.
 */
class ShopService
{
    public function __construct(
        private AuditLogger $audit,
        private PhoneNumberNormalizer $phones,
    ) {}

    /**
     * New shops start as drafts awaiting mall approval.
     *
     * @param  array{slug: string, name: string, description?: string|null, contact_phone?: string|null}  $data
     */
    public function create(Mall $mall, ShopCategory $category, array $data): Shop
    {
        if ($mall->status === MallStatus::Archived) {
            throw new BusinessRuleViolation('shop.mall_archived', __('Shops cannot be added to an archived mall.'));
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug']) !== 1) {
            throw new BusinessRuleViolation('shop.invalid_slug', __('A shop slug may only contain lower-case letters, digits and single hyphens.'));
        }

        if ($mall->shops()->where('slug', $data['slug'])->exists()) {
            throw new BusinessRuleViolation('shop.slug_taken', __('Another shop in this mall already uses this slug.'));
        }

        if (filled($data['contact_phone'] ?? null) && $this->phones->normalize($data['contact_phone']) === null) {
            throw new BusinessRuleViolation('shop.invalid_contact_phone', __('Enter a valid contact number, for example 01712345678.'));
        }

        return DB::transaction(function () use ($mall, $category, $data): Shop {
            $shop = $mall->shops()->create([
                ...$data,
                'shop_category_id' => $category->getKey(),
                'status' => ShopStatus::Draft,
            ]);

            $this->audit->record('shop.created', $shop, ['slug' => $shop->slug], $mall->getKey(), $shop->getKey());

            return $shop;
        });
    }

    /**
     * Places a shop in a physical unit. The floor must belong to the shop's own
     * mall, an ERD rule a foreign key cannot express.
     */
    public function assignUnit(Shop $shop, Floor $floor, string $unitCode, ?float $areaSqM = null): ShopUnit
    {
        if ($floor->mall_id !== $shop->mall_id) {
            throw new BusinessRuleViolation('shop_unit.floor_in_other_mall', __('The floor belongs to another mall.'));
        }

        if ($floor->status !== 'active') {
            throw new BusinessRuleViolation('shop_unit.floor_archived', __('Units cannot be assigned on an archived floor.'));
        }

        $unitCode = Str::upper(trim($unitCode));

        if (preg_match('/^[A-Z0-9][A-Z0-9-]{0,39}$/', $unitCode) !== 1) {
            throw new BusinessRuleViolation('shop_unit.invalid_code', __('A unit code uses letters, digits and hyphens, e.g. G-05.'));
        }

        if ($floor->shopUnits()->where('unit_code', $unitCode)->exists()) {
            throw new BusinessRuleViolation('shop_unit.code_taken', __('Unit :code on this floor is already assigned.', ['code' => $unitCode]));
        }

        if ($areaSqM !== null && $areaSqM <= 0) {
            throw new BusinessRuleViolation('shop_unit.invalid_area', __('The unit area must be greater than zero.'));
        }

        return DB::transaction(function () use ($shop, $floor, $unitCode, $areaSqM): ShopUnit {
            $unit = $shop->units()->create([
                'floor_id' => $floor->getKey(),
                'unit_code' => $unitCode,
                'area_sq_m' => $areaSqM,
                'status' => 'open',
            ]);

            $this->audit->record('shop.unit_assigned', $shop, ['unit_code' => $unitCode, 'floor' => $floor->code], $shop->mall_id, $shop->getKey());

            return $unit;
        });
    }

    /**
     * Replaces the shop's weekly opening hours. Times are local to the mall's
     * timezone; a weekday without intervals means closed.
     *
     * @param  list<array{weekday: int, opens_at: string, closes_at: string, closes_next_day?: bool}>  $intervals
     */
    public function setOpeningHours(Shop $shop, array $intervals): void
    {
        $ranges = [];

        foreach ($intervals as $interval) {
            $ranges = [...$ranges, ...$this->weeklyRanges($interval)];
        }

        usort($ranges, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        for ($i = 1; $i < count($ranges); $i++) {
            if ($ranges[$i][0] < $ranges[$i - 1][1]) {
                throw new BusinessRuleViolation('opening_hour.overlap', __('Opening intervals overlap.'));
            }
        }

        $rows = collect($intervals)
            ->sortBy([['weekday', 'asc'], ['opens_at', 'asc']])
            ->groupBy('weekday')
            ->flatMap(fn ($day) => $day->values()->map(fn (array $interval, int $index): array => [
                'shop_id' => $shop->getKey(),
                'weekday' => $interval['weekday'],
                'interval_no' => $index + 1,
                'opens_at' => $interval['opens_at'],
                'closes_at' => $interval['closes_at'],
                'closes_next_day' => (bool) ($interval['closes_next_day'] ?? false),
            ]))
            ->values()
            ->all();

        DB::transaction(function () use ($shop, $rows): void {
            $shop->openingHours()->delete();
            ShopOpeningHour::query()->insert($rows);

            $this->audit->record('shop.hours_changed', $shop, ['intervals' => count($rows)], $shop->mall_id, $shop->getKey());
        });
    }

    public function changeStatus(Shop $shop, ShopStatus $to, ?string $reason = null): Shop
    {
        $from = $shop->status;

        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw new BusinessRuleViolation('shop.invalid_status_transition', __('A :from shop cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
        }

        DB::transaction(function () use ($shop, $from, $to, $reason): void {
            $shop->update(['status' => $to]);

            $this->audit->record('shop.status_changed', $shop, array_filter([
                'from' => $from->value,
                'to' => $to->value,
                'reason' => $reason,
            ]), $shop->mall_id, $shop->getKey());

            ShopStatusChanged::dispatch($shop->getKey(), $shop->mall_id, $from, $to);
        });

        return $shop;
    }

    /**
     * Converts one interval into [start, end) minute ranges on a Monday-00:00
     * weekly timeline, splitting Sunday-night intervals that wrap into Monday.
     *
     * @param  array{weekday: int, opens_at: string, closes_at: string, closes_next_day?: bool}  $interval
     * @return list<array{0: int, 1: int}>
     */
    private function weeklyRanges(array $interval): array
    {
        $weekday = (int) $interval['weekday'];

        if ($weekday < 1 || $weekday > 7) {
            throw new BusinessRuleViolation('opening_hour.invalid_weekday', __('Weekdays run from 1 (Monday) to 7 (Sunday).'));
        }

        $opens = $this->minutes($interval['opens_at']);
        $closes = $this->minutes($interval['closes_at']);
        $nextDay = (bool) ($interval['closes_next_day'] ?? false);

        if (! $nextDay && $closes <= $opens) {
            throw new BusinessRuleViolation('opening_hour.closes_before_opening', __('Closing time must be after opening time, or mark the interval as closing the next day.'));
        }

        $start = ($weekday - 1) * 1440 + $opens;
        $end = match (true) {
            ! $nextDay => ($weekday - 1) * 1440 + $closes,
            $closes === $opens => $start + 1440,
            default => $weekday * 1440 + $closes,
        };

        $week = 7 * 1440;

        return $end <= $week ? [[$start, $end]] : [[$start, $week], [0, $end - $week]];
    }

    private function minutes(string $time): int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::00)?$/', $time, $parts) !== 1) {
            throw new BusinessRuleViolation('opening_hour.invalid_time', __(':time is not a valid 24-hour time.', ['time' => $time]));
        }

        return ((int) $parts[1]) * 60 + (int) $parts[2];
    }
}
