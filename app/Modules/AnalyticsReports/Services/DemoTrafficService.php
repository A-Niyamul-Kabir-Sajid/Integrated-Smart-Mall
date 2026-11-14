<?php

namespace App\Modules\AnalyticsReports\Services;

use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Writes clearly labelled demonstration traffic so dashboards are not empty on a demo database.
 * Every row carries the demo label, the numbers come from a fixed seed (the same data every time),
 * and re-running replaces the earlier demonstration rows without touching real ones.
 */
class DemoTrafficService
{
    /**
     * Terms with the number of results a search for them returns in the demo catalogue (0 = not found).
     *
     * @var array<string, bool>
     */
    public const TERMS = [
        'coffee' => true, 'shoes' => true, 'phone' => true, 'rice' => true, 'book' => true,
        'charger' => false, 'umbrella' => false, 'toy' => false, 'gift card' => false,
    ];

    public function __construct(private ActivityRollupService $rollups) {}

    /**
     * @return array{search: int, view: int}
     */
    public function seed(Mall $mall, int $days = 30, int $seed = 2026): array
    {
        $random = new Randomizer(new Mt19937($seed));
        $this->clear($mall);

        $shops = $mall->shops()->where('status', ShopStatus::Active->value)->pluck('shop_id')->all();
        $products = Product::query()->whereHas('variants.listings', fn ($listings) => $listings->whereIn('shop_id', $shops))->pluck('product_id')->all();
        $categories = DB::table('shop_categories')->pluck('slug')->all();
        $floors = $mall->floors()->pluck('code')->all();
        $terms = array_keys(self::TERMS);

        $searches = $views = [];
        $today = CarbonImmutable::now($mall->timezone)->startOfDay();

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $today->subDays($offset);

            for ($i = 0, $count = $random->getInt(20, 45); $i < $count; $i++) {
                $term = $terms[$random->getInt(0, count($terms) - 1)];
                $found = self::TERMS[$term];
                $moment = $day->addSeconds($random->getInt(8 * 3600, 21 * 3600))->utc();

                $searches[] = [
                    'mall_id' => $mall->getKey(),
                    'kind' => $random->getInt(0, 1) === 0 ? 'shop' : 'product',
                    'term' => $term,
                    'term_masked' => false,
                    'category' => $categories !== [] && $random->getInt(0, 3) === 0 ? $categories[$random->getInt(0, count($categories) - 1)] : null,
                    'floor' => $floors !== [] && $random->getInt(0, 3) === 0 ? $floors[$random->getInt(0, count($floors) - 1)] : null,
                    'result_count' => $found ? $random->getInt(1, 12) : 0,
                    'signed_in' => $random->getInt(0, 2) === 0,
                    'dataset_label' => DatasetLabel::Demo->value,
                    'occurred_at' => $moment,
                ];
            }

            foreach ($shops as $shopId) {
                for ($i = 0, $count = $random->getInt(3, 25); $i < $count; $i++) {
                    $views[] = $this->view($mall, 'shop', $shopId, null, $day, $random, $i);
                }
            }

            foreach ($products as $productId) {
                for ($i = 0, $count = $random->getInt(1, 15); $i < $count; $i++) {
                    $views[] = $this->view($mall, 'product', null, $productId, $day, $random, $i);
                }
            }
        }

        foreach (array_chunk($searches, 500) as $chunk) {
            DB::table('activity_search_events')->insert($chunk);
        }

        foreach (array_chunk($views, 500) as $chunk) {
            DB::table('activity_view_events')->insert($chunk);
        }

        $this->rollups->rollupRange($mall, $today->subDays($days - 1), $today);

        return ['search' => count($searches), 'view' => count($views)];
    }

    /**
     * Removes the demonstration events and summaries of the mall; real rows stay.
     */
    public function clear(Mall $mall): void
    {
        DB::table('activity_search_events')->where('mall_id', $mall->getKey())->where('dataset_label', DatasetLabel::Demo->value)->delete();
        DB::table('activity_view_events')->where('mall_id', $mall->getKey())->where('dataset_label', DatasetLabel::Demo->value)->delete();
        DB::table('activity_daily_summaries')->where('mall_id', $mall->getKey())->where('dataset_label', DatasetLabel::Demo->value)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function view(Mall $mall, string $kind, ?string $shopId, ?string $productId, CarbonImmutable $day, Randomizer $random, int $index): array
    {
        return [
            'mall_id' => $mall->getKey(),
            'kind' => $kind,
            'shop_id' => $shopId,
            'product_id' => $productId,
            'visitor_key' => hash('sha256', 'demo|'.$day->format('Y-m-d').'|'.$kind.'|'.($shopId ?? $productId).'|'.($index % 12)),
            'dataset_label' => DatasetLabel::Demo->value,
            'occurred_at' => $day->addSeconds($random->getInt(8 * 3600, 21 * 3600))->utc(),
        ];
    }
}
