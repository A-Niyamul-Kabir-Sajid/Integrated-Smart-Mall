<?php

namespace App\Core\Modules;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base class for each module's service provider.
 *
 * Loads the module's Routes/web.php with the "web" middleware group and
 * Routes/api.php under the "api" prefix and middleware group, and registers
 * the listeners declared in $listen. Subclasses register bindings in
 * register() and policies or route model bindings in boot(), calling
 * parent::boot().
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Events from any module that this module reacts to: event class => listener classes.
     *
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [];

    public function boot(): void
    {
        $this->loadModuleRoutes();
        $this->registerModuleListeners();
    }

    /**
     * The module's folder name, e.g. "MallShopDirectory", taken from the provider's namespace.
     */
    public function moduleName(): string
    {
        $segments = explode('\\', static::class);

        return $segments[2] ?? class_basename(static::class);
    }

    /**
     * Absolute path of the module directory.
     */
    public function modulePath(string $path = ''): string
    {
        return app_path('Modules/'.$this->moduleName()).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    protected function registerModuleListeners(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    protected function loadModuleRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $webRoutes = $this->modulePath('Routes/web.php');

        if (is_file($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }

        $apiRoutes = $this->modulePath('Routes/api.php');

        if (is_file($apiRoutes)) {
            Route::middleware('api')->prefix('api')->group($apiRoutes);
        }
    }
}
