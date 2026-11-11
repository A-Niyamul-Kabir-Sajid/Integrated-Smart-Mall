<?php

use App\Modules\AccountsAccess\Providers\AccountsAccessServiceProvider;
use App\Modules\FavouritesPreferences\Providers\FavouritesPreferencesServiceProvider;
use App\Modules\MallShopDirectory\Providers\MallShopDirectoryServiceProvider;
use App\Modules\ParkingFacilities\Providers\ParkingFacilitiesServiceProvider;
use App\Modules\ProductsCatalogue\Providers\ProductsCatalogueServiceProvider;
use App\Modules\PurchaseVerification\Providers\PurchaseVerificationServiceProvider;
use App\Modules\ReviewsModeration\Providers\ReviewsModerationServiceProvider;
use App\Modules\SharedPlatform\Providers\SharedPlatformServiceProvider;
use App\Modules\ShopOwnerOperations\Providers\ShopOwnerOperationsServiceProvider;

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
        'MallShopDirectory' => MallShopDirectoryServiceProvider::class,
        'ProductsCatalogue' => ProductsCatalogueServiceProvider::class,
        'PurchaseVerification' => PurchaseVerificationServiceProvider::class,
        'ReviewsModeration' => ReviewsModerationServiceProvider::class,
        'ShopOwnerOperations' => ShopOwnerOperationsServiceProvider::class,
        'FavouritesPreferences' => FavouritesPreferencesServiceProvider::class,
        'ParkingFacilities' => ParkingFacilitiesServiceProvider::class,
    ],

];
