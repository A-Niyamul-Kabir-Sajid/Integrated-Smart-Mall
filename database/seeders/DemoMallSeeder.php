<?php

namespace Database\Seeders;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Synthetic demonstration data for one fictional mall. Names, brands and prices
 * are invented; never use this seeder for tests (use factories).
 */
class DemoMallSeeder extends Seeder
{
    public const MALL_SLUG = 'demo-city-mall';

    /**
     * Shop category slug => name.
     */
    public const SHOP_CATEGORIES = [
        'food-drinks' => 'Food & Drinks',
        'fashion' => 'Fashion',
        'electronics' => 'Electronics',
        'groceries' => 'Groceries',
        'health-beauty' => 'Health & Beauty',
        'books-stationery' => 'Books & Stationery',
        'services' => 'Services',
    ];

    /** @var array<string, Floor> */
    protected array $floors = [];

    /** @var array<string, ShopCategory> */
    protected array $shopCategories = [];

    /** @var array<string, Shop> */
    protected array $shops = [];

    public function run(): void
    {
        $mall = Mall::query()->create([
            'slug' => self::MALL_SLUG,
            'name' => 'Demo City Mall',
            'address' => 'Demo Road 32, Sonadanga',
            'city' => 'Khulna',
            'timezone' => 'Asia/Dhaka',
            'status' => MallStatus::Active,
        ]);

        $this->seedFloors($mall);
        $this->seedShopCategories();
        $this->seedShops($mall);
    }

    private function seedFloors(Mall $mall): void
    {
        foreach ([['G', 'Ground Floor', 0, 0.0], ['F1', 'First Floor', 1, 4.5]] as [$code, $name, $order, $elevation]) {
            $this->floors[$code] = $mall->floors()->create([
                'code' => $code,
                'name' => $name,
                'sort_order' => $order,
                'elevation_m' => $elevation,
                'status' => 'active',
            ]);
        }
    }

    private function seedShopCategories(): void
    {
        foreach (self::SHOP_CATEGORIES as $slug => $name) {
            $this->shopCategories[$slug] = ShopCategory::query()->create(['slug' => $slug, 'name' => $name]);
        }
    }

    private function seedShops(Mall $mall): void
    {
        $everyDay = fn (string $opens, string $closes, bool $nextDay = false): array => array_map(
            fn (int $day): array => [$day, 1, $opens, $closes, $nextDay],
            range(1, 7),
        );

        $shops = [
            ['Cafe Brew', 'food-drinks', 'Coffee, tea and fresh pastries.', ['G-01'], 'active', $everyDay('08:00', '23:00')],
            ['Fresh Mart', 'groceries', 'Everyday groceries, snacks and cold drinks.', ['G-02'], 'active', $everyDay('09:00', '22:00')],
            ['Gadget Hub', 'electronics', 'Phones, chargers, audio and accessories.', ['G-03'], 'active', $everyDay('10:00', '21:00')],
            ['Style Avenue', 'fashion', 'Casual and festive wear for men and women.', ['G-04'], 'active',
                array_map(fn (int $day): array => [$day, 1, '10:00', '21:00', false], [1, 3, 4, 5, 6, 7])],
            ['Juice Corner', 'food-drinks', 'Fresh juices and smoothies, open late.', ['G-05'], 'active', $everyDay('10:00', '01:00', true)],
            ['Pharma Care', 'health-beauty', 'Pharmacy and personal care, open 24 hours.', ['G-06'], 'active', $everyDay('00:00', '00:00', true)],
            ['Phone Fix', 'services', 'Phone repairs and screen replacement.', ['G-07'], 'active', $everyDay('11:00', '20:00')],
            ['Book Nook', 'books-stationery', 'Books, notebooks and art supplies.', ['F1-01'], 'active', [
                ...array_map(fn (int $day): array => [$day, 1, '10:00', '21:00', false], [1, 2, 3, 4, 6, 7]),
                [5, 1, '15:00', '21:00', false],
            ]],
            ['Little Steps', 'fashion', 'Clothing and shoes for children.', ['F1-02'], 'active', $everyDay('10:00', '21:00')],
            ['Sports Zone', 'fashion', 'Sportswear and running shoes.', ['F1-03'], 'closed', []],
            ['Glow Beauty', 'health-beauty', 'Skin care and cosmetics.', ['F1-04'], 'active', $everyDay('10:00', '21:00')],
            ['Food Court', 'food-drinks', 'Lunch and dinner from several counters.', ['F1-05', 'F1-06'], 'active', [
                ...array_map(fn (int $day): array => [$day, 1, '11:00', '15:00', false], range(1, 7)),
                ...array_map(fn (int $day): array => [$day, 2, '17:00', '23:30', false], range(1, 7)),
            ]],
            ['Easy Bank Service Point', 'services', 'Cash withdrawal and account services.', ['F1-07'], 'active',
                array_map(fn (int $day): array => [$day, 1, '10:00', '17:00', false], [1, 2, 3, 4, 7])],
            ['Tech Galaxy', 'electronics', 'Awaiting mall approval.', [], 'draft', []],
        ];

        foreach ($shops as [$name, $category, $description, $unitCodes, $status, $hours]) {
            $shop = $mall->shops()->create([
                'shop_category_id' => $this->shopCategories[$category]->getKey(),
                'slug' => Str::slug($name),
                'name' => $name,
                'description' => $description,
                'contact_phone' => '+8801700'.str_pad((string) (count($this->shops) + 1), 6, '0', STR_PAD_LEFT),
                'status' => $status,
            ]);

            foreach ($unitCodes as $unitCode) {
                $shop->units()->create([
                    'floor_id' => $this->floors[Str::before($unitCode, '-')]->getKey(),
                    'unit_code' => $unitCode,
                    'area_sq_m' => 137.5,
                    'status' => 'open',
                ]);
            }

            ShopOpeningHour::query()->insert(array_map(fn (array $interval): array => [
                'shop_id' => $shop->getKey(),
                'weekday' => $interval[0],
                'interval_no' => $interval[1],
                'opens_at' => $interval[2],
                'closes_at' => $interval[3],
                'closes_next_day' => $interval[4],
            ], $hours));

            $this->shops[$name] = $shop;
        }
    }
}
