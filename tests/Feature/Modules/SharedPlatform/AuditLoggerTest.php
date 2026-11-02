<?php

namespace Tests\Feature\Modules\SharedPlatform;

use App\Core\Middleware\AssignRequestId;
use App\Models\User;
use App\Modules\SharedPlatform\Models\AuditLog;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_actor_subject_scope_and_request_context(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        Route::middleware('web')->post('/_test/audit', function (AuditLogger $audit) use ($subject) {
            $audit->record('account.role_assigned', $subject, ['role' => 'mall_admin'], mallId: 'mall-1', shopId: null);

            return response()->noContent();
        });

        $this->actingAs($actor)
            ->post('/_test/audit', [], [AssignRequestId::HEADER => 'trace-audit-01', 'User-Agent' => 'TestBrowser/1.0'])
            ->assertNoContent();

        $record = AuditLog::query()->sole();
        $this->assertSame('account.role_assigned', $record->action);
        $this->assertSame((string) $actor->getKey(), $record->actor_id);
        $this->assertSame('users', $record->subject_type);
        $this->assertSame((string) $subject->getKey(), $record->subject_id);
        $this->assertSame('mall-1', $record->mall_id);
        $this->assertNull($record->shop_id);
        $this->assertSame('trace-audit-01', $record->request_id);
        $this->assertSame('127.0.0.1', $record->ip_address);
        $this->assertSame('TestBrowser/1.0', $record->user_agent);
        $this->assertSame(['role' => 'mall_admin'], $record->metadata);
    }

    public function test_system_actions_have_no_actor(): void
    {
        $record = app(AuditLogger::class)->record('report.generated');

        $this->assertNull($record->actor_id);
        $this->assertNull($record->subject_type);
    }

    public function test_secret_looking_metadata_is_redacted_at_any_depth(): void
    {
        $record = app(AuditLogger::class)->record('account.password_changed', metadata: [
            'password' => 'new-secret-1',
            'channel' => 'web',
            'previous' => ['api_key' => 'abc', 'remember_token' => 'xyz', 'email' => 'user@example.com'],
        ]);

        $this->assertSame([
            'password' => '[redacted]',
            'channel' => 'web',
            'previous' => ['api_key' => '[redacted]', 'remember_token' => '[redacted]', 'email' => 'user@example.com'],
        ], $record->fresh()->metadata);
    }

    public function test_rejects_actions_that_are_not_dotted_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AuditLogger::class)->record('Shop Approved');
    }
}
