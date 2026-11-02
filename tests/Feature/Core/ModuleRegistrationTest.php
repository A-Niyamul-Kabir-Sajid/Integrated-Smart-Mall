<?php

namespace Tests\Feature\Core;

use App\Core\Modules\ModuleServiceProvider;
use Tests\TestCase;

class ModuleRegistrationTest extends TestCase
{
    public function test_every_listed_module_exists_and_its_provider_is_registered(): void
    {
        $providers = config('modules.providers');

        $this->assertNotEmpty($providers);

        foreach ($providers as $module => $provider) {
            $this->assertDirectoryExists(app_path("Modules/{$module}"), "config/modules.php lists unknown module {$module}.");
            $this->assertTrue(is_subclass_of($provider, ModuleServiceProvider::class), "{$provider} must extend ModuleServiceProvider.");
            $this->assertSame($module, (new $provider($this->app))->moduleName(), "{$provider} must live in its own module.");
            $this->assertNotNull($this->app->getProvider($provider), "{$provider} is not registered.");
        }
    }

    public function test_every_module_provider_on_disk_is_listed_in_the_manifest(): void
    {
        $listed = array_values(config('modules.providers'));

        foreach (glob(app_path('Modules/*/Providers/*ServiceProvider.php')) as $file) {
            $module = basename(dirname($file, 2));
            $class = "App\\Modules\\{$module}\\Providers\\".basename($file, '.php');

            $this->assertContains($class, $listed, "{$class} exists but is missing from config/modules.php.");
        }
    }
}
