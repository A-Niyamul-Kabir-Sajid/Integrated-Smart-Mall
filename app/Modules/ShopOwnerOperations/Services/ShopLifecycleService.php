<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Services\ShopService;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use Illuminate\Support\Facades\Gate;

/**
 * Suspension and reactivation of an active shop by the mall's administrators.
 * A suspended shop is "closed": listings stay visible but the shop cannot take
 * orders or use the operational areas, and only the mall can reopen it (decision D-014).
 */
class ShopLifecycleService
{
    public function __construct(
        private ShopService $shops,
        private MembershipService $memberships,
    ) {}

    public function suspend(User $administrator, Shop $shop, string $reason): Shop
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $shop->mall);

        $reason = trim($reason);

        if (mb_strlen($reason) < ShopApplicationService::REASON_MIN || mb_strlen($reason) > 1000) {
            throw new BusinessRuleViolation('shop.suspension_reason_required', __('Write a reason of :min to 1000 characters.', ['min' => ShopApplicationService::REASON_MIN]));
        }

        if ($shop->status !== ShopStatus::Active) {
            throw new BusinessRuleViolation('shop.not_active', __('Only an active shop can be suspended.'));
        }

        $this->shops->changeStatus($shop, ShopStatus::Closed, 'suspended: '.$reason);
        $this->tellOwners($shop, __('Your shop was suspended'), $reason);

        return $shop;
    }

    public function reactivate(User $administrator, Shop $shop): Shop
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $shop->mall);

        if ($shop->status !== ShopStatus::Closed) {
            throw new BusinessRuleViolation('shop.not_closed', __('Only a suspended shop can be reactivated.'));
        }

        $this->shops->changeStatus($shop, ShopStatus::Active, 'reactivated');
        $this->tellOwners($shop, __('Your shop is active again'), __(':shop can take orders again.', ['shop' => $shop->name]));

        return $shop;
    }

    private function tellOwners(Shop $shop, string $title, string $body): void
    {
        foreach ($this->memberships->shopOwners($shop) as $owner) {
            $owner->notify(new SystemNotice($title, $body, route('workspace.dashboard', $shop)));
        }
    }
}
