<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Enums\FacilityStatus;
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

    /**
     * Renames a floor or changes where it sits in the list. The code is the floor's identity in
     * URLs, maps and filters, so it never changes.
     */
    public function update(Floor $floor, string $name, int $sortOrder, float $elevationMetres): Floor
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 80) {
            throw new BusinessRuleViolation('floor.invalid_name', __('Enter a floor name of up to 80 characters.'));
        }

        $floor->update(['name' => $name, 'sort_order' => $sortOrder, 'elevation_m' => $elevationMetres]);

        $this->audit->record('floor.updated', $floor, ['fields' => array_keys($floor->getChanges())], mallId: $floor->mall_id);

        return $floor;
    }

    /**
     * A floor that still holds a shop unit or a facility cannot be archived: those records
     * would be left on a level that no longer exists for customers.
     */
    public function archive(Floor $floor): Floor
    {
        if ($floor->status !== 'active') {
            throw new BusinessRuleViolation('floor.not_active', __('This floor is already archived.'));
        }

        if ($floor->shopUnits()->where('status', '!=', 'archived')->exists()) {
            throw new BusinessRuleViolation('floor.has_units', __('Move or release the shop units on this floor before archiving it.'));
        }

        if ($floor->facilities()->where('status', '!=', FacilityStatus::Archived->value)->exists()) {
            throw new BusinessRuleViolation('floor.has_facilities', __('Move or archive the facilities on this floor before archiving it.'));
        }

        $floor->update(['status' => 'archived']);

        $this->audit->record('floor.archived', $floor, ['code' => $floor->code], mallId: $floor->mall_id);

        return $floor;
    }

    public function restore(Floor $floor): Floor
    {
        if ($floor->status !== 'archived') {
            throw new BusinessRuleViolation('floor.not_archived', __('This floor is not archived.'));
        }

        $floor->update(['status' => 'active']);

        $this->audit->record('floor.restored', $floor, ['code' => $floor->code], mallId: $floor->mall_id);

        return $floor;
    }
}
