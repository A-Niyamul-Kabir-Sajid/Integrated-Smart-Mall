<?php

namespace App\Modules\AccountsAccess\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Enums\RoleScope;
use App\Modules\AccountsAccess\Models\MallMembership;
use App\Modules\AccountsAccess\Models\Role;
use App\Modules\AccountsAccess\Models\ShopMembership;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grants and revokes mall- and shop-scoped roles. Revoking keeps the row
 * (status revoked) so history stays meaningful; granting again reactivates it.
 */
class MembershipService
{
    public function __construct(private AuditLogger $audit) {}

    public function grantMallRole(User $user, Mall $mall, RoleCode $code): MallMembership
    {
        $role = $this->roleWithScope($code, RoleScope::Mall);

        $membership = MallMembership::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'mall_id' => $mall->getKey(),
            'role_id' => $role->getKey(),
        ]);

        if ($membership->exists && $membership->status === 'active') {
            return $membership;
        }

        $membership->fill(['status' => 'active', 'assigned_at' => now()])->save();

        $this->audit->record('access.role_assigned', $user, ['role' => $code->value, 'scope' => 'mall'], mallId: $mall->getKey());

        return $membership;
    }

    public function revokeMallRole(User $user, Mall $mall, RoleCode $code): void
    {
        $updated = MallMembership::query()->active()
            ->where(['user_id' => $user->getKey(), 'mall_id' => $mall->getKey(), 'role_id' => Role::forCode($code)->getKey()])
            ->update(['status' => 'revoked']);

        if ($updated > 0) {
            $this->audit->record('access.role_revoked', $user, ['role' => $code->value, 'scope' => 'mall'], mallId: $mall->getKey());
        }
    }

    public function grantShopRole(User $user, Shop $shop, RoleCode $code): ShopMembership
    {
        $role = $this->roleWithScope($code, RoleScope::Shop);

        $membership = ShopMembership::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'shop_id' => $shop->getKey(),
            'role_id' => $role->getKey(),
        ]);

        if ($membership->exists && $membership->status === 'active') {
            return $membership;
        }

        $membership->fill(['status' => 'active', 'assigned_at' => now()])->save();

        $this->audit->record('access.role_assigned', $user, ['role' => $code->value, 'scope' => 'shop'], $shop->mall_id, $shop->getKey());

        return $membership;
    }

    public function revokeShopRole(User $user, Shop $shop, RoleCode $code): void
    {
        $updated = ShopMembership::query()->active()
            ->where(['user_id' => $user->getKey(), 'shop_id' => $shop->getKey(), 'role_id' => Role::forCode($code)->getKey()])
            ->update(['status' => 'revoked']);

        if ($updated > 0) {
            $this->audit->record('access.role_revoked', $user, ['role' => $code->value, 'scope' => 'shop'], $shop->mall_id, $shop->getKey());
        }
    }

    /**
     * Whether the account runs a mall or a shop (any active mall or shop role). Their own page
     * visits are work, not customer interest, so visitor statistics leave them out.
     */
    public function holdsOperatingRole(User $user): bool
    {
        return MallMembership::query()->active()->where('user_id', $user->getKey())->exists()
            || ShopMembership::query()->active()->where('user_id', $user->getKey())->exists();
    }

    /**
     * Active administrators of the mall, for notices that need a person to act.
     *
     * @return Collection<int, User>
     */
    public function mallAdministrators(Mall $mall): Collection
    {
        return User::query()->whereIn('user_id', MallMembership::query()->active()
            ->where('mall_id', $mall->getKey())
            ->whereHas('role', fn ($role) => $role->where('code', RoleCode::MallAdmin->value))
            ->select('user_id'))->get();
    }

    /**
     * Active owners of the shop.
     *
     * @return Collection<int, User>
     */
    public function shopOwners(Shop $shop): Collection
    {
        return User::query()->whereIn('user_id', ShopMembership::query()->active()
            ->where('shop_id', $shop->getKey())
            ->whereHas('role', fn ($role) => $role->where('code', RoleCode::ShopOwner->value))
            ->select('user_id'))->get();
    }

    private function roleWithScope(RoleCode $code, RoleScope $scope): Role
    {
        if ($code->scope() !== $scope) {
            throw new BusinessRuleViolation(
                "{$scope->value}_membership.wrong_role_scope",
                __('The :role role cannot be granted for a :scope.', ['role' => $code->value, 'scope' => $scope->value]),
            );
        }

        return Role::forCode($code);
    }
}
