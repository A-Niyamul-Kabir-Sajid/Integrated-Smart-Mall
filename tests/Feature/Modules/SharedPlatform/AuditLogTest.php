<?php

namespace Tests\Feature\Modules\SharedPlatform;

use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function record(): AuditLog
    {
        return AuditLog::query()->create([
            'occurred_at' => now(),
            'action' => 'shop.status_changed',
            'metadata' => ['from' => 'draft', 'to' => 'active'],
        ]);
    }

    public function test_records_can_be_created_and_read_back(): void
    {
        $record = $this->record()->fresh();

        $this->assertSame('shop.status_changed', $record->action);
        $this->assertSame(['from' => 'draft', 'to' => 'active'], $record->metadata);
    }

    public function test_records_cannot_be_updated(): void
    {
        $record = $this->record();

        $this->expectException(LogicException::class);

        $record->update(['action' => 'shop.deleted']);
    }

    public function test_records_cannot_be_deleted(): void
    {
        $record = $this->record();

        try {
            $record->delete();
            $this->fail('Deleting an audit record must throw.');
        } catch (LogicException) {
            $this->assertDatabaseCount('audit_logs', 1);
        }
    }
}
