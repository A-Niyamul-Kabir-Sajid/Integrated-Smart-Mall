<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Support\Str;

/**
 * Maintains the physical floors of a mall.
 */
class FloorService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Codes are stored upper-case (G, F1, B2); sort order controls display only.
     */
    public function addFloor(Mall $mall, string $code, string $name, int $sortOrder, float $elevationMetres): Floor
    {
        if ($mall->status === MallStatus::Archived) {
            throw new BusinessRuleViolation('floor.mall_archived', __('Floors cannot be added to an archived mall.'));
        }

        $code = Str::upper(trim($code));

        if (preg_match('/^[A-Z0-9]{1,20}$/', $code) !== 1) {
            throw new BusinessRuleViolation('floor.invalid_code', __('A floor code uses up to 20 letters and digits, e.g. G, F1 or B2.'));
        }

        if ($mall->floors()->where('code', $code)->exists()) {
            throw new BusinessRuleViolation('floor.code_taken', __('This mall already has a floor with code :code.', ['code' => $code]));
        }

        $floor = $mall->floors()->create([
            'code' => $code,
            'name' => trim($name),
            'sort_order' => $sortOrder,
            'elevation_m' => $elevationMetres,
            'status' => 'active',
        ]);

        $this->audit->record('floor.created', $floor, ['code' => $code], mallId: $mall->getKey());

        return $floor;
    }
}
