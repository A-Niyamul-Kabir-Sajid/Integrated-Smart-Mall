<?php

namespace Tests\Feature\Core;

use App\Core\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_web_responses_carry_the_security_headers(): void
    {
        $response = $this->get('/');

        foreach (SecurityHeaders::HEADERS as $name => $value) {
            $response->assertHeader($name, $value);
        }

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_api_responses_carry_the_security_headers(): void
    {
        Route::middleware('api')->get('/api/_test/headers', fn () => ['ok' => true]);

        $response = $this->getJson('/api/_test/headers');

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_https_responses_enable_strict_transport_security(): void
    {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
