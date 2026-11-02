<?php

namespace Tests\Feature\Core;

use App\Core\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Fixtures\Modules\Example\Events\ExampleRecordChanged;
use Tests\Fixtures\Modules\Example\Listeners\RememberExampleChange;
use Tests\TestCase;

class ModuleEventFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RememberExampleChange::$handled = [];

        $this->app->register(new class($this->app) extends ModuleServiceProvider
        {
            protected array $listen = [
                ExampleRecordChanged::class => [RememberExampleChange::class],
            ];

            public function modulePath(string $path = ''): string
            {
                return base_path('tests/Fixtures/Modules/Example').($path === '' ? '' : '/'.$path);
            }
        });
    }

    public function test_listener_declared_by_a_module_provider_handles_the_event(): void
    {
        ExampleRecordChanged::dispatch('record-1', 'active');

        $this->assertSame(['record-1:active'], RememberExampleChange::$handled);
    }

    public function test_event_raised_inside_a_transaction_reaches_listeners_only_after_commit(): void
    {
        DB::transaction(function (): void {
            ExampleRecordChanged::dispatch('record-2', 'closed');

            $this->assertSame([], RememberExampleChange::$handled);
        });

        $this->assertSame(['record-2:closed'], RememberExampleChange::$handled);
    }

    public function test_event_raised_inside_a_rolled_back_transaction_never_reaches_listeners(): void
    {
        try {
            DB::transaction(function (): void {
                ExampleRecordChanged::dispatch('record-3', 'archived');

                throw new RuntimeException('Write failed.');
            });
        } catch (RuntimeException) {
            // The failed write is the scenario under test.
        }

        $this->assertSame([], RememberExampleChange::$handled);
    }
}
