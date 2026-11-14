<?php

namespace App\Modules\AnalyticsReports\Services;

use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Models\SearchEvent;
use App\Modules\AnalyticsReports\Models\ViewEvent;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\ActivityType;
use App\Modules\SharedPlatform\Events\VisitorActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Records searches and page views as anonymous events (Jira EPIC-14, decision D-016).
 *
 * Left out on purpose: requests that are not a browser, accounts that operate a shop or the mall,
 * empty searches, and a page the same visitor opened in the last few minutes. Real traffic is
 * always labelled real; demonstration traffic is written by DemoTrafficService instead.
 */
class ActivityRecorder
{
    public function __construct(
        private SearchTermSanitizer $terms,
        private CrawlerDetector $crawlers,
        private VisitorKey $visitorKeys,
        private MembershipService $memberships,
    ) {}

    public function record(VisitorActivity $activity, Request $request): void
    {
        if ($this->crawlers->isCrawler($request->userAgent())) {
            return;
        }

        $user = $request->user();

        if ($user !== null && $this->memberships->holdsOperatingRole($user)) {
            return;
        }

        $timezone = Mall::query()->whereKey($activity->mallId)->value('timezone');

        if ($timezone === null) {
            return;
        }

        $visitor = $this->visitorKeys->for($request, Carbon::now((string) $timezone));

        if ($activity->type->isSearch()) {
            $this->recordSearch($activity, $visitor, $user !== null);

            return;
        }

        $this->recordView($activity, $visitor);
    }

    private function recordSearch(VisitorActivity $activity, string $visitor, bool $signedIn): void
    {
        ['term' => $term, 'masked' => $masked] = $this->terms->sanitize($activity->term);

        if ($term === null && ! $masked) {
            return;
        }

        // The same visitor repeating a search (a reload, a back button) is one search.
        $repeat = hash('sha256', $visitor.'|'.$activity->type->value.'|'.($term ?? 'masked:'.mb_strtolower(trim((string) $activity->term))).'|'.$activity->category.'|'.$activity->floor);

        if (! $this->firstInWindow('search', $repeat)) {
            return;
        }

        SearchEvent::query()->create([
            'mall_id' => $activity->mallId,
            'kind' => $activity->type === ActivityType::ShopSearch ? 'shop' : 'product',
            'term' => $term,
            'term_masked' => $masked,
            'category' => filled($activity->category) ? mb_substr($activity->category, 0, 80) : null,
            'floor' => filled($activity->floor) ? mb_substr($activity->floor, 0, 20) : null,
            'result_count' => max(0, (int) $activity->resultCount),
            'signed_in' => $signedIn,
            'dataset_label' => DatasetLabel::Real,
            'occurred_at' => now(),
        ]);
    }

    private function recordView(VisitorActivity $activity, string $key): void
    {
        $subject = $activity->type === ActivityType::ShopView ? $activity->shopId : $activity->productId;

        if ($subject === null || ! $this->firstInWindow('view', hash('sha256', $key.'|'.$activity->type->value.'|'.$subject))) {
            return;
        }

        ViewEvent::query()->create([
            'mall_id' => $activity->mallId,
            'kind' => $activity->type === ActivityType::ShopView ? 'shop' : 'product',
            'shop_id' => $activity->type === ActivityType::ShopView ? $activity->shopId : null,
            'product_id' => $activity->type === ActivityType::ProductView ? $activity->productId : null,
            'visitor_key' => $key,
            'dataset_label' => DatasetLabel::Real,
            'occurred_at' => now(),
        ]);
    }

    /**
     * True the first time a fingerprint is seen within the de-duplication window, false for repeats.
     */
    private function firstInWindow(string $scope, string $fingerprint): bool
    {
        return Cache::add("activity-{$scope}:{$fingerprint}", 1, now()->addMinutes(max(1, (int) config('analytics.dedup_minutes', 30))));
    }
}
