<?php

namespace Tests\Feature\Core;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\SharedPlatform\Providers\SharedPlatformServiceProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(new class($this->app) extends ModuleServiceProvider
        {
            public function modulePath(string $path = ''): string
            {
                return base_path('tests/Fixtures/Modules/Example').($path === '' ? '' : '/'.$path);
            }
        });

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_web_routes_are_loaded_with_the_web_middleware_group(): void
    {
        $route = Route::getRoutes()->getByName('module-fixture.web');

        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
        $this->get('/_module-fixture')->assertOk()->assertSee('web fixture');
    }

    public function test_api_routes_are_loaded_under_the_api_prefix_and_middleware_group(): void
    {
        $route = Route::getRoutes()->getByName('module-fixture.api');

        $this->assertNotNull($route);
        $this->assertSame('api/module-fixture', $route->uri());
        $this->assertContains('api', $route->gatherMiddleware());
        $this->getJson('/api/module-fixture')->assertOk()->assertExactJson(['source' => 'api fixture']);
    }

    public function test_module_name_is_derived_from_the_provider_namespace(): void
    {
        $provider = new SharedPlatformServiceProvider($this->app);

        $this->assertSame('SharedPlatform', $provider->moduleName());
        $this->assertSame(app_path('Modules'.DIRECTORY_SEPARATOR.'SharedPlatform'), str_replace('/', DIRECTORY_SEPARATOR, $provider->modulePath()));
    }
}
