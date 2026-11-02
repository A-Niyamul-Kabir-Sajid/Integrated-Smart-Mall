<?php

namespace Tests\Feature\Core;

use App\Core\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignRequestIdTest extends TestCase
{
    public function test_responses_carry_a_generated_request_id(): void
    {
        $requestId = $this->get('/up')->assertOk()->headers->get(AssignRequestId::HEADER);

        $this->assertTrue(Str::isUuid($requestId));
    }

    public function test_a_well_formed_incoming_request_id_is_reused(): void
    {
        $this->get('/up', [AssignRequestId::HEADER => 'proxy-trace-12345'])
            ->assertHeader(AssignRequestId::HEADER, 'proxy-trace-12345');
    }

    public function test_a_malformed_incoming_request_id_is_replaced(): void
    {
        $requestId = $this->get('/up', [AssignRequestId::HEADER => "bad id\n<script>"])->headers->get(AssignRequestId::HEADER);

        $this->assertTrue(Str::isUuid($requestId));
    }

    public function test_log_lines_written_during_the_request_share_the_request_id(): void
    {
        Route::middleware('web')->get('/_test/log', fn () => Log::sharedContext());

        $response = $this->get('/_test/log', [AssignRequestId::HEADER => 'trace-abcdef12']);

        $response->assertJson([AssignRequestId::ATTRIBUTE => 'trace-abcdef12']);
    }
}
