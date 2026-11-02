<?php

use App\Modules\AccountsAccess\Providers\AccountsAccessServiceProvider;
use App\Modules\FavouritesPreferences\Providers\FavouritesPreferencesServiceProvider;
use App\Modules\SharedPlatform\Providers\SharedPlatformServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled application modules
    |--------------------------------------------------------------------------
    |
    | Module folder name (app/Modules/<Module>) => the module's service provider,
    | registered in this order. List a module when it gains its first route,
    | binding or listener. The test suite fails when a module provider exists
    | on disk but is missing here, or when an entry points at no module.
    |
    */

    'providers' => [
        'SharedPlatform' => SharedPlatformServiceProvider::class,
        'AccountsAccess' => AccountsAccessServiceProvider::class,
        'FavouritesPreferences' => FavouritesPreferencesServiceProvider::class,
    ],

];
