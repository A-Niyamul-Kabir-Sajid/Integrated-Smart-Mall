<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FloorService;
use App\Modules\MallShopDirectory\Services\MallService;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The mall administrators' maintenance of the mall itself and its floors (Jira ISM-110/ISM-112,
 * decision D-015). The rules live in MallShopDirectory; this service checks that the caller
 * administers the mall in question and that a floor belongs to it.
 */
class MallAdministrationService
{
    public function __construct(
        private MallService $malls,
        private FloorService $floors,
    ) {}

    /**
     * @param  array{name: string, address: string, city: string, timezone: string}  $data
     */
    public function updateProfile(User $administrator, Mall $mall, array $data): Mall
    {
        $this->authorize($administrator, $mall);

        return $this->malls->updateProfile($mall, $data);
    }

    /**
     * Opens a draft mall to the public or archives it; the allowed moves are the mall lifecycle's.
     */
    public function changeStatus(User $administrator, Mall $mall, MallStatus $to): Mall
    {
        $this->authorize($administrator, $mall);

        return $this->malls->changeStatus($mall, $to);
    }

    /**
     * @return Collection<int, Floor>
     */
    public function floorsOf(User $administrator, Mall $mall): Collection
    {
        $this->authorize($administrator, $mall);

        return $mall->floors()->withCount([
            'shopUnits as units_count' => fn ($units) => $units->where('status', '!=', 'archived'),
            'facilities as facilities_count' => fn ($facilities) => $facilities->where('status', '!=', 'archived'),
        ])->get();
    }

    public function addFloor(User $administrator, Mall $mall, string $code, string $name, int $sortOrder, float $elevationMetres): Floor
    {
        $this->authorize($administrator, $mall);

        return $this->floors->addFloor($mall, $code, $name, $sortOrder, $elevationMetres);
    }

    public function updateFloor(User $administrator, Mall $mall, Floor $floor, string $name, int $sortOrder, float $elevationMetres): Floor
    {
        $this->authorizeFloor($administrator, $mall, $floor);

        return $this->floors->update($floor, $name, $sortOrder, $elevationMetres);
    }

    public function archiveFloor(User $administrator, Mall $mall, Floor $floor): Floor
    {
        $this->authorizeFloor($administrator, $mall, $floor);

        return $this->floors->archive($floor);
    }

    public function restoreFloor(User $administrator, Mall $mall, Floor $floor): Floor
    {
        $this->authorizeFloor($administrator, $mall, $floor);

        return $this->floors->restore($floor);
    }

    private function authorize(User $administrator, Mall $mall): void
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);
    }

    private function authorizeFloor(User $administrator, Mall $mall, Floor $floor): void
    {
        $this->authorize($administrator, $mall);

        abort_unless($floor->mall_id === $mall->getKey(), 404);
    }
}
