<?php

namespace Tests\Fixtures\Modules\Example\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fixture domain event following DOCS/11_MODULE_CONTRACTS.md: past tense,
 * identifier payload, dispatched only after the surrounding transaction commits.
 */
final class ExampleRecordChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $recordId, public readonly string $newStatus) {}
}
