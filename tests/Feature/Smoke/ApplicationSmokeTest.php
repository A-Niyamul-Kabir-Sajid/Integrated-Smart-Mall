<?php

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_reports_the_application_as_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_home_page_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_application_stores_times_in_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_web_and_health_routes_are_registered(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route): string => $route->uri());

        $this->assertContains('/', $uris);
        $this->assertContains('up', $uris);
    }

    public function test_database_connection_works_and_migrations_ran(): void
    {
        $this->assertSame(1, (int) DB::selectOne('select 1 as ok')->ok);

        foreach (['migrations', 'users', 'sessions', 'cache', 'jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table {$table} after migrating.");
        }
    }
}
