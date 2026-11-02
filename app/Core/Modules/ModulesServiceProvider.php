<?php

namespace App\Core\Modules;

use Illuminate\Support\ServiceProvider;

/**
 * Registers every module provider listed in config/modules.php.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ((array) config('modules.providers', []) as $provider) {
            $this->app->register($provider);
        }
    }
}
