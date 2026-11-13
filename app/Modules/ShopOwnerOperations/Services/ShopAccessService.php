<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\ShopMembership;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use Illuminate\Auth\Access\Response;

/**
 * Decides which workspace areas a member may use (decision D-014): owners use every
 * area, staff only the areas their work function permits, and nobody operates a
 * shop that is not active. The platform administrator is treated like an owner.
 */
class ShopAccessService
{
    public function check(User $user, Shop $shop, ShopArea $area): Response
    {
        if (! $user->isActive()) {
            return Response::deny();
        }

        if ($shop->status !== ShopStatus::Active) {
            return Response::deny(__('This shop is not active, so this area is unavailable.'));
        }

        if ($this->isOwner($user, $shop)) {
            return Response::allow();
        }

        $function = $this->functionOf($user, $shop);

        return $function !== null && $function->permits($area) ? Response::allow() : Response::deny();
    }

    /**
     * The areas the member may use now, for the workspace menu.
     *
     * @return list<ShopArea>
     */
    public function areasFor(User $user, Shop $shop): array
    {
        return array_values(array_filter(ShopArea::cases(), fn (ShopArea $area): bool => $this->check($user, $shop, $area)->allowed()));
    }

    /**
     * The staff member's recorded function, or null for owners, platform administrators
     * and staff without one.
     */
    public function functionOf(User $user, Shop $shop): ?ShopFunction
    {
        $row = ShopStaffFunction::query()
            ->whereIn('shop_membership_id', ShopMembership::query()->active()
                ->where('user_id', $user->getKey())
                ->where('shop_id', $shop->getKey())
                ->whereHas('role', fn ($role) => $role->where('code', RoleCode::ShopStaff->value))
                ->select('shop_membership_id'))
            ->first();

        return $row?->staff_function;
    }

    private function isOwner(User $user, Shop $shop): bool
    {
        return $user->hasPlatformRole(RoleCode::PlatformAdmin) || $user->hasShopRole($shop->getKey(), RoleCode::ShopOwner);
    }
}
