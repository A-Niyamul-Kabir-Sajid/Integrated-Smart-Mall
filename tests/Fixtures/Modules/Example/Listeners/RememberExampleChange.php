<?php

namespace Tests\Fixtures\Modules\Example\Listeners;

use Tests\Fixtures\Modules\Example\Events\ExampleRecordChanged;

final class RememberExampleChange
{
    /**
     * @var list<string>
     */
    public static array $handled = [];

    public function handle(ExampleRecordChanged $event): void
    {
        self::$handled[] = $event->recordId.':'.$event->newStatus;
    }
}
