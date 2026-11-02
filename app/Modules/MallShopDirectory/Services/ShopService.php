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
}
