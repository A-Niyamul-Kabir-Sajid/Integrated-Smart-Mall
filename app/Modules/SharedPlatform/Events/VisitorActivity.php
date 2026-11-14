<?php

namespace App\Modules\SharedPlatform\Events;

use App\Modules\SharedPlatform\Enums\ActivityType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A visitor searched or opened a public page. Public pages call report(), which hands the fact
 * to whichever module counts it (AnalyticsReports) without the page depending on that module.
 *
 * The event carries no person: the recorder reads the request itself to build an anonymous key.
 */
final class VisitorActivity
{
    use Dispatchable;

    public function __construct(
        public readonly ActivityType $type,
        public readonly string $mallId,
        public readonly ?string $shopId = null,
        public readonly ?string $productId = null,
        public readonly ?string $term = null,
        public readonly ?int $resultCount = null,
        public readonly ?string $category = null,
        public readonly ?string $floor = null,
    ) {}

    /**
     * Reports the activity and swallows every failure: counting visitors must never break a page.
     */
    public static function report(self $activity): void
    {
        try {
            event($activity);
        } catch (Throwable $exception) {
            Log::warning('Visitor activity was not recorded.', ['type' => $activity->type->value, 'error' => $exception->getMessage()]);
        }
    }
}
