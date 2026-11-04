<?php

namespace App\Modules\AccountsAccess\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Http\Middleware\EnsureAccountIsActive;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registers AccountsAccess: registration, sign-in, profiles and scoped access.
 */
class AccountsAccessServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', EnsureAccountIsActive::class);

        $this->defineAbilities();

        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(5)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * Maps the shared abilities onto platform roles and active memberships.
     * A role name alone never grants access to every mall or shop.
     */
    private function defineAbilities(): void
    {
        $isPlatformAdmin = fn (User $user): bool => $user->isActive() && $user->hasPlatformRole(RoleCode::PlatformAdmin);

        Gate::define(Ability::AdministerPlatform, $isPlatformAdmin);

        Gate::define(Ability::AdministerMall, fn (User $user, Mall $mall): bool => $isPlatformAdmin($user)
            || ($user->isActive() && $user->hasMallRole($mall->getKey(), RoleCode::MallAdmin)));

        Gate::define(Ability::ManageShop, fn (User $user, Shop $shop): bool => $isPlatformAdmin($user)
            || ($user->isActive() && $user->hasShopRole($shop->getKey(), RoleCode::ShopOwner)));

        Gate::define(Ability::WorkInShop, fn (User $user, Shop $shop): bool => $isPlatformAdmin($user)
            || ($user->isActive() && $user->hasShopRole($shop->getKey(), RoleCode::ShopOwner, RoleCode::ShopStaff)));
    }
}
