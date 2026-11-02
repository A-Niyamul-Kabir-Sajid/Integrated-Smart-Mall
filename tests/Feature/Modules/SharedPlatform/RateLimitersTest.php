<?php

namespace Tests\Feature\Modules\SharedPlatform;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RateLimitersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'throttle:login'])->post('/_test/login', fn () => 'attempt');
    }

    #[TestWith(['login'])]
    #[TestWith(['registration'])]
    #[TestWith(['password-reset'])]
    #[TestWith(['search'])]
    public function test_named_limiter_is_defined(string $name): void
    {
        $this->assertNotNull(RateLimiter::limiter($name));
    }

    public function test_login_limiter_blocks_the_sixth_attempt_per_login_within_a_minute(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/_test/login', ['login' => 'customer@example.com'])->assertOk();
        }

        $this->post('/_test/login', ['login' => 'Customer@Example.com '])->assertTooManyRequests();
    }

    public function test_login_limiter_counts_each_login_separately(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/_test/login', ['login' => 'first@example.com'])->assertOk();
        }

        $this->post('/_test/login', ['login' => 'second@example.com'])->assertOk();
    }
}
