<?php

namespace Database\Seeders;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Simulated sales, orders, reviews, complaints and stock for the demo mall, so the dashboards and the
 * forecast prototype have history to work on (Jira EPIC-12, decision D-017). Everything is marked as
 * simulated: receipts and orders start with SIM-, complaints with SIM-, reviews come from accounts at
 * sim.demo.test. Reports count these records and say so. The numbers come from a fixed seed, and running
 * the seeder again replaces the earlier simulated records without touching anything else.
 *
 * Built-in stories for the demonstration: one shop's sales fall sharply in the last month, one shop's
 * ratings decline, one shop has several old unresolved complaints, one shop has a single unusually busy day.
 */
class DemoSalesHistorySeeder extends Seeder
{
    public const DAYS = 150;

    private const REVIEWERS = 20;

    private const DROP_SHOP = 'Phone Fix';

    private const SPIKE_SHOP = 'Fresh Mart';

    private const DECLINING_SHOP = 'Cafe Brew';

    private const COMPLAINT_SHOP = 'Book Nook';

    /** @var list<float> Monday to Sunday */
    private const WEEKDAY_FACTOR = [0.8, 0.85, 0.9, 1.0, 1.25, 1.5, 1.3];

    private const POSITIVE = ['Friendly staff and great quality', 'Great prices and quick service', 'Lovely shop and a helpful cashier', 'Excellent quality, will come again', 'Fresh stock and friendly service'];

    private const NEGATIVE = ['Slow checkout and rude staff', 'Overpriced and the queue was slow', 'Rude cashier and wrong change', 'Out of stock again and very slow service', 'Dirty floor and rude staff'];

    private const NEUTRAL = ['Average experience, nothing special', 'Okay prices and a decent selection'];

    private Randomizer $random;

    public function run(): void
    {
        $mall = Mall::query()->where('slug', DemoMallSeeder::MALL_SLUG)->first();
        $customer = User::query()->where('email', 'customer@demo.test')->first();

        if ($mall === null || $customer === null) {
            return;
        }

        $this->random = new Randomizer(new Mt19937(2027));
        $this->clear();

        $shops = DB::table('shops')->where('mall_id', $mall->getKey())->where('status', 'active')->orderBy('name')->get(['shop_id', 'name']);
        $today = CarbonImmutable::now($mall->timezone)->startOfDay();
        $reviewers = $this->reviewers();

        foreach ($shops as $index => $shop) {
            $listings = DB::table('shop_products')->where('shop_id', $shop->shop_id)->where('status', 'active')->get(['shop_product_id', 'price_amount']);

            if ($listings->isEmpty()) {
                continue;
            }

            $this->stock($shop->shop_id, $listings);
            $this->sales($shop->shop_id, $index, $shop->name, $listings, $today, $mall->timezone);
            $this->orders($shop->shop_id, $customer->getKey(), $listings, $today, $mall->timezone);
            $this->reviews($shop->shop_id, $index, $shop->name === self::DECLINING_SHOP, $reviewers, $today);
        }

        $this->complaints($mall, $customer, $shops->pluck('shop_id')->all(), (string) $shops->firstWhere('name', self::COMPLAINT_SHOP)?->shop_id, $today);
    }

    private function clear(): void
    {
        $purchases = DB::table('purchases')->where('external_receipt_ref', 'like', 'SIM-%')->pluck('purchase_id');

        foreach ($purchases->chunk(500) as $chunk) {
            DB::table('purchase_items')->whereIn('purchase_id', $chunk)->delete();
        }

        DB::table('purchases')->where('external_receipt_ref', 'like', 'SIM-%')->delete();

        $orders = DB::table('shop_orders')->where('order_number', 'like', 'SIM-%')->pluck('shop_order_id');

        foreach ($orders->chunk(500) as $chunk) {
            DB::table('shop_order_items')->whereIn('shop_order_id', $chunk)->delete();
        }

        DB::table('shop_orders')->where('order_number', 'like', 'SIM-%')->delete();
        DB::table('complaints')->where('complaint_number', 'like', 'SIM-%')->delete();

        $reviewers = DB::table('users')->where('email', 'like', '%@sim.demo.test')->pluck('user_id');
        DB::table('reviews')->whereIn('author_user_id', $reviewers)->delete();
    }

    /**
     * @return list<string> user ids of the simulated reviewers
     */
    private function reviewers(): array
    {
        $ids = [];

        for ($i = 1; $i <= self::REVIEWERS; $i++) {
            $email = sprintf('reviewer%02d@sim.demo.test', $i);
            $ids[] = (string) (User::query()->where('email', $email)->first() ?? User::factory()->create(['email' => $email, 'display_name' => 'Simulated reviewer '.$i]))->getKey();
        }

        return $ids;
    }

    /**
     * @param  Collection<int, object>  $listings
     */
    private function stock(string $shopId, $listings): void
    {
        foreach ($listings as $listing) {
            $roll = $this->random->getInt(1, 10);
            DB::table('listing_stocks')->updateOrInsert(
                ['shop_product_id' => $listing->shop_product_id],
                ['shop_id' => $shopId, 'on_hand' => $roll === 1 ? 0 : ($roll === 2 ? 3 : $this->random->getInt(12, 80)), 'low_stock_threshold' => 5, 'updated_at' => now()],
            );
        }
    }

    /**
     * @param  Collection<int, object>  $listings
     */
    private function sales(string $shopId, int $index, string $shopName, $listings, CarbonImmutable $today, string $timezone): void
    {
        $base = 1.6 + ($index % 5) * 0.9;
        $growth = [0.45, 0.1, 0.3, -0.05, 0.2, 0.35][$index % 6];
        $purchases = $items = [];
        $counter = 0;

        for ($offset = self::DAYS; $offset >= 1; $offset--) {
            $day = $today->subDays($offset);
            $factor = self::WEEKDAY_FACTOR[$day->dayOfWeekIso - 1] * (1 + $growth * (self::DAYS - $offset) / self::DAYS);

            if ($shopName === self::DROP_SHOP && $offset <= 30) {
                $factor *= 0.3;
            }

            if ($shopName === self::SPIKE_SHOP && $offset === 20) {
                $factor *= 6;
            }

            $lambda = $base * $factor;
            $count = max(0, (int) round($lambda + ($this->random->getInt(-100, 100) / 100) * sqrt($lambda)));

            for ($n = 1; $n <= $count; $n++) {
                $id = (string) Str::uuid();
                $moment = $day->addSeconds($this->random->getInt(9 * 3600, 21 * 3600))->utc();
                $total = 0.0;

                for ($line = 1, $lines = $this->random->getInt(1, 3); $line <= $lines; $line++) {
                    $listing = $listings[$this->random->getInt(0, $listings->count() - 1)];
                    $quantity = $this->random->getInt(1, 3);
                    $amount = round($quantity * (float) $listing->price_amount, 2);
                    $total += $amount;
                    $items[] = [
                        'purchase_item_id' => (string) Str::uuid(), 'purchase_id' => $id, 'line_no' => $line, 'shop_product_id' => $listing->shop_product_id,
                        'product_label_snapshot' => 'Simulated item', 'quantity' => $quantity, 'unit_price_amount' => $listing->price_amount, 'line_discount_amount' => '0.00', 'line_total_amount' => number_format($amount, 2, '.', ''),
                    ];
                }

                $roll = $this->random->getInt(1, 100);
                $purchases[] = [
                    'purchase_id' => $id, 'shop_id' => $shopId, 'buyer_user_id' => null, 'external_receipt_ref' => sprintf('SIM-%d-%03d-%03d', $index, self::DAYS - $offset, ++$counter),
                    'status' => $roll <= 94 ? 'completed' : ($roll <= 97 ? 'voided' : 'refunded'), 'currency' => 'BDT', 'total_amount' => number_format($total, 2, '.', ''),
                    'purchased_at' => $moment->format('Y-m-d H:i:s'), 'recorded_at' => $moment->format('Y-m-d H:i:s'),
                ];
            }
        }

        foreach (array_chunk($purchases, 200) as $chunk) {
            DB::table('purchases')->insert($chunk);
        }

        foreach (array_chunk($items, 200) as $chunk) {
            DB::table('purchase_items')->insert($chunk);
        }

        unset($timezone);
    }

    /**
     * @param  Collection<int, object>  $listings
     */
    private function orders(string $shopId, string $customerId, $listings, CarbonImmutable $today, string $timezone): void
    {
        $statuses = ['completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'cancelled', 'cancelled', 'rejected', 'expired', 'requested', 'accepted'];
        $orders = $items = [];

        for ($offset = 60; $offset >= 1; $offset--) {
            if ($this->random->getInt(1, 100) > 55) {
                continue;
            }

            $id = (string) Str::uuid();
            $moment = $today->subDays($offset)->addSeconds($this->random->getInt(9 * 3600, 20 * 3600))->utc();
            $status = $statuses[$this->random->getInt(0, count($statuses) - 1)];
            $listing = $listings[$this->random->getInt(0, $listings->count() - 1)];
            $quantity = $this->random->getInt(1, 2);
            $amount = number_format($quantity * (float) $listing->price_amount, 2, '.', '');

            $orders[] = [
                'shop_order_id' => $id, 'order_number' => 'SIM-'.strtoupper(bin2hex(random_bytes(4))), 'shop_id' => $shopId, 'customer_user_id' => $customerId,
                'kind' => $this->random->getInt(1, 100) <= 40 ? 'reservation' : 'click_and_collect', 'status' => $status, 'currency' => 'BDT',
                'subtotal_amount' => $amount, 'discount_amount' => '0.00', 'total_amount' => $amount, 'pickup_code' => strtoupper(bin2hex(random_bytes(3))),
                'pickup_by' => $moment->addDay()->format('Y-m-d H:i:s'), 'created_at' => $moment->format('Y-m-d H:i:s'), 'updated_at' => $moment->format('Y-m-d H:i:s'),
                'completed_at' => $status === 'completed' ? $moment->addHours(5)->format('Y-m-d H:i:s') : null,
            ];
            $items[] = [
                'shop_order_item_id' => (string) Str::uuid(), 'shop_order_id' => $id, 'line_no' => 1, 'shop_product_id' => $listing->shop_product_id,
                'label_snapshot' => 'Simulated item', 'quantity' => $quantity, 'unit_price_amount' => $listing->price_amount, 'line_total_amount' => $amount,
            ];
        }

        DB::table('shop_orders')->insert($orders);
        DB::table('shop_order_items')->insert($items);

        unset($timezone);
    }

    /**
     * @param  list<string>  $reviewers
     */
    private function reviews(string $shopId, int $index, bool $declining, array $reviewers, CarbonImmutable $today): void
    {
        $count = $declining ? 12 : $this->random->getInt(3, 14);
        $mean = [4.6, 4.2, 3.9, 4.4, 3.6, 4.1][$index % 6];
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $daysAgo = $declining ? 110 - $i * 9 : $this->random->getInt(2, 140);
            $noise = $this->random->getInt(-100, 100) / 100;
            $rating = (int) max(1, min(5, round($declining ? 4.8 - 0.28 * $i + $noise * 0.6 : $mean + $noise * 1.2)));
            $pool = $rating >= 4 ? self::POSITIVE : ($rating <= 2 ? self::NEGATIVE : self::NEUTRAL);
            $at = $today->subDays($daysAgo)->addHours(14)->utc()->format('Y-m-d H:i:s');

            $rows[] = [
                'review_id' => (string) Str::uuid(), 'author_user_id' => $reviewers[$i % count($reviewers)], 'shop_id' => $shopId, 'shop_product_id' => null, 'rating' => $rating,
                'title' => null, 'body' => $pool[$this->random->getInt(0, count($pool) - 1)], 'status' => 'published', 'created_at' => $at, 'updated_at' => $at,
            ];
        }

        DB::table('reviews')->insert($rows);
    }

    /**
     * @param  list<string>  $shopIds
     */
    private function complaints(Mall $mall, User $customer, array $shopIds, string $complaintShop, CarbonImmutable $today): void
    {
        $categories = ['cleanliness', 'cleanliness', 'cleanliness', 'cleanliness', 'shop_service', 'shop_service', 'shop_service', 'facilities', 'facilities', 'noise', 'safety', 'staff_conduct'];
        $rows = [];

        for ($i = 1; $i <= 14; $i++) {
            $filed = $today->subDays($this->random->getInt(1, 55))->addHours($this->random->getInt(9, 20))->utc();
            $status = ['open', 'assigned', 'in_progress', 'resolved', 'closed', 'resolved', 'closed'][$this->random->getInt(0, 6)];
            $rows[] = $this->complaintRow($mall, $customer, $i, $categories[$this->random->getInt(0, count($categories) - 1)], $this->random->getInt(1, 100) <= 60 ? $shopIds[$this->random->getInt(0, count($shopIds) - 1)] : null, $filed, $status);
        }

        // One shop with four complaints that stayed unresolved for weeks.
        foreach ([9, 13, 18, 24] as $n => $daysAgo) {
            $rows[] = $this->complaintRow($mall, $customer, 20 + $n, 'shop_service', $complaintShop, $today->subDays($daysAgo)->addHours(11)->utc(), $n % 2 === 0 ? 'open' : 'assigned');
        }

        DB::table('complaints')->insert($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function complaintRow(Mall $mall, User $customer, int $number, string $category, ?string $shopId, CarbonImmutable $filed, string $status): array
    {
        $closed = in_array($status, ['resolved', 'closed'], true) ? $filed->addHours($this->random->getInt(6, 120)) : null;

        return [
            'complaint_id' => (string) Str::uuid(), 'complaint_number' => sprintf('SIM-C%04d', $number), 'mall_id' => $mall->getKey(), 'customer_user_id' => $customer->getKey(), 'shop_id' => $shopId,
            'category' => $category, 'subject' => 'Simulated complaint '.$number, 'description' => 'A simulated complaint used to demonstrate the reports.', 'status' => $status,
            'assigned_to_user_id' => null, 'resolution_note' => $closed === null ? null : 'Simulated resolution.', 'created_at' => $filed->format('Y-m-d H:i:s'),
            'updated_at' => ($closed ?? $filed)->format('Y-m-d H:i:s'), 'closed_at' => $closed?->format('Y-m-d H:i:s'),
        ];
    }
}
