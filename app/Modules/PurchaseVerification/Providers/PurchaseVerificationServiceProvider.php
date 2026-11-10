<?php

namespace App\Modules\PurchaseVerification\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\PurchaseVerification\Contracts\PurchaseVerifier;
use App\Modules\PurchaseVerification\Services\TokenVerificationService;

/**
 * Registers PurchaseVerification: recorded sales, purchase credentials and their redemption.
 */
class PurchaseVerificationServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PurchaseVerifier::class, TokenVerificationService::class);
    }
}
