<?php

namespace App\Modules\MallShopDirectory\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Support\Facades\Route;

/**
 * Registers MallShopDirectory: malls, floors, shops and the public directory.
 */
class MallShopDirectoryServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Public pages address malls by slug and only ever resolve active malls.
        Route::bind('publicMall', fn (string $slug): Mall => Mall::query()->active()->where('slug', $slug)->firstOrFail());
    }
}
