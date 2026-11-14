<?php

namespace App\Modules\MallOwnerAdmin\Providers;

use App\Core\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registers MallOwnerAdmin: the mall administrators' workspace, which calls the owning
 * modules' services on behalf of authorised administrators (decision D-015).
 */
class MallOwnerAdminServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('mall-admin', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
