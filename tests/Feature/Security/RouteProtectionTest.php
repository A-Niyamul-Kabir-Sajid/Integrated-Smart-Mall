<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every application route needs a deliberate access decision: it requires
 * sign-in, is guest-only, or is listed here as public. A new route without one
 * fails this test instead of silently shipping unprotected.
 */
class RouteProtectionTest extends TestCase
{
    /**
     * Route URIs anyone may use, signed in or not.
     */
    private const PUBLIC_URIS = [
        '/',
        'locale',
        'up',
    ];

    /**
     * Guest-only credential endpoints and the throttle each must carry.
     */
    private const THROTTLED_GUEST_POSTS = [
        'login' => 'login',
        'register' => 'registration',
        'forgot-password' => 'password-reset',
        'reset-password' => 'password-reset',
    ];

    /**
     * @return list<RouteDefinition>
     */
    private function applicationRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RouteDefinition $route): bool => ! str_starts_with($route->uri(), '_') && ! str_starts_with($route->uri(), 'storage/'),
        ));
    }

    public function test_every_route_is_public_by_decision_guest_only_or_behind_sign_in(): void
    {
        foreach ($this->applicationRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $decided = in_array($route->uri(), self::PUBLIC_URIS, true)
                || in_array('auth', $middleware, true)
                || in_array('guest', $middleware, true);

            $this->assertTrue($decided, "Route [{$route->uri()}] has no access decision: add auth, guest or list it as public.");
        }
    }

    public function test_credential_endpoints_are_throttled(): void
    {
        foreach (self::THROTTLED_GUEST_POSTS as $uri => $limiter) {
            $route = collect($this->applicationRoutes())->first(fn (RouteDefinition $route): bool => $route->uri() === $uri && in_array('POST', $route->methods(), true));

            $this->assertNotNull($route, "POST {$uri} is missing.");
            $this->assertContains("throttle:{$limiter}", $route->gatherMiddleware(), "POST {$uri} must use throttle:{$limiter}.");
        }
    }

    public function test_account_pages_require_sign_in(): void
    {
        foreach (['account.show', 'account.profile.edit', 'account.password.edit', 'account.preferences.edit'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }
}
