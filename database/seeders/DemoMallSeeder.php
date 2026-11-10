<?php

namespace Database\Seeders;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopOpeningHour;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Synthetic demonstration data for one fictional mall. Names, brands and prices
 * are invented; never use this seeder for tests (use factories).
 */
class DemoMallSeeder extends Seeder
{
    public const MALL_SLUG = 'niagara-city-mall';

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
            'name' => 'Niagara City Mall',
            'address' => 'Demo Road 32, Sonadanga',
            'city' => 'Khulna',
            'timezone' => 'Asia/Dhaka',
            'status' => MallStatus::Active,
        ]);

        $this->seedFloors($mall);
        $this->seedShopCategories();
        $this->seedShops($mall);
        $this->seedCatalogue();
    }

    /**
     * Product categories, shared products with variants, and each shop's listings
     * (price in BDT). Listings of shops that are not active stay archived.
     */
    private function seedCatalogue(): void
    {
        $categories = [];

        foreach ([
            'drinks' => 'Drinks', 'snacks-meals' => 'Snacks & Meals', 'phone-accessories' => 'Phone Accessories',
            'audio' => 'Audio', 'clothing' => 'Clothing', 'footwear' => 'Footwear', 'personal-care' => 'Personal Care',
            'medicine' => 'Medicine', 'books' => 'Books', 'stationery' => 'Stationery', 'groceries' => 'Groceries',
        ] as $slug => $name) {
            $categories[$slug] = ProductCategory::query()->create(['slug' => $slug, 'name' => $name]);
        }

        $catalogue = [
            ['Mineral Water', 'AquaPure', 'drinks', 'Still drinking water.', [
                ['500ml', '500 ml bottle', ['volume' => '500 ml'], ['Fresh Mart' => 20, 'Juice Corner' => 25, 'Pharma Care' => 20, 'Food Court' => 30]],
                ['1500ml', '1.5 litre bottle', ['volume' => '1.5 L'], ['Fresh Mart' => 35]],
            ]],
            ['Cola Soft Drink', 'Fizzo', 'drinks', 'Chilled carbonated cola.', [
                ['250ml', '250 ml can', ['volume' => '250 ml'], ['Fresh Mart' => 40, 'Juice Corner' => 45, 'Food Court' => 50]],
            ]],
            ['Mango Juice', 'Sonali Orchard', 'drinks', 'Fresh mango juice, served cold.', [
                ['regular', 'Regular cup', ['size' => 'Regular'], ['Juice Corner' => 120]],
                ['bottle', '250 ml bottle', ['volume' => '250 ml'], ['Fresh Mart' => 60]],
            ]],
            ['Cold Coffee', 'Cafe Brew', 'drinks', 'Iced coffee with milk.', [
                ['regular', 'Regular', ['size' => 'Regular'], ['Cafe Brew' => 220]],
                ['large', 'Large', ['size' => 'Large'], ['Cafe Brew' => 280]],
            ]],
            ['Espresso', 'Cafe Brew', 'drinks', 'Single-shot espresso.', [['default', 'Single shot', [], ['Cafe Brew' => 150]]]],
            ['Chicken Burger', null, 'snacks-meals', 'Grilled chicken burger with fries.', [['default', 'Burger with fries', [], ['Food Court' => 350]]]],
            ['Butter Croissant', null, 'snacks-meals', 'Baked fresh every morning.', [['default', 'One piece', [], ['Cafe Brew' => 160]]]],
            ['USB-C Charger 20W', 'VoltEdge', 'phone-accessories', 'Fast wall charger with USB-C port.', [
                ['default', '20 W', ['power' => '20 W'], ['Gadget Hub' => 1450, 'Phone Fix' => 1350]],
            ]],
            ['Tempered Glass Screen Protector', 'VoltEdge', 'phone-accessories', 'Fits most 6.1–6.7 inch phones.', [
                ['default', 'Universal', [], ['Gadget Hub' => 350, 'Phone Fix' => 300]],
            ]],
            ['Wireless Earbuds', 'VoltEdge', 'audio', 'Bluetooth earbuds with charging case.', [
                ['black', 'Black', ['colour' => 'Black'], ['Gadget Hub' => 2900]],
                ['white', 'White', ['colour' => 'White'], ['Gadget Hub' => 2900]],
            ]],
            ['Cotton T-Shirt', 'Northloom', 'clothing', 'Plain cotton crew-neck T-shirt.', [
                ['s', 'Size S', ['size' => 'S'], ['Style Avenue' => 650, 'Sports Zone' => 700]],
                ['m', 'Size M', ['size' => 'M'], ['Style Avenue' => 650, 'Sports Zone' => 700]],
                ['l', 'Size L', ['size' => 'L'], ['Style Avenue' => 650]],
            ]],
            ['Cotton Panjabi', 'Northloom', 'clothing', 'Festive cotton panjabi.', [
                ['m', 'Size M', ['size' => 'M'], ['Style Avenue' => 2400]],
                ['l', 'Size L', ['size' => 'L'], ['Style Avenue' => 2400]],
            ]],
            ['Kids Sneakers', 'Little Steps', 'footwear', 'Lightweight sneakers for children.', [['30', 'EU 30', ['size' => 'EU 30'], ['Little Steps' => 1800]]]],
            ['Running Shoes', 'Stride', 'footwear', 'Cushioned shoes for daily running.', [['42', 'EU 42', ['size' => 'EU 42'], ['Sports Zone' => 4800]]]],
            ['Sunscreen SPF 50', 'SunShield', 'personal-care', 'Broad-spectrum sunscreen, 50 ml.', [
                ['50ml', '50 ml tube', ['volume' => '50 ml'], ['Glow Beauty' => 950, 'Pharma Care' => 900]],
            ]],
            ['Paracetamol 500 mg', null, 'medicine', 'Strip of 10 tablets.', [['strip', 'Strip of 10', [], ['Pharma Care' => 12]]]],
            ['A5 Notebook', 'Lekha', 'stationery', '120-page ruled notebook.', [['default', '120 pages', [], ['Book Nook' => 90, 'Fresh Mart' => 100]]]],
            ['Bangla–English Dictionary', 'Lekha', 'books', 'Pocket bilingual dictionary.', [['default', 'Paperback', [], ['Book Nook' => 450]]]],
            ['Miniket Rice', null, 'groceries', 'Polished rice.', [['5kg', '5 kg bag', ['weight' => '5 kg'], ['Fresh Mart' => 420]]]],
        ];

        $skuNumber = 0;

        foreach ($catalogue as [$name, $brand, $category, $description, $variants]) {
            $product = Product::query()->create([
                'product_category_id' => $categories[$category]->getKey(),
                'name' => $name,
                'brand' => $brand,
                'description' => $description,
                'status' => 'active',
            ]);

            foreach ($variants as [$code, $label, $attributes, $prices]) {
                $variant = $product->variants()->create([
                    'variant_code' => $code,
                    'label' => $label,
                    'attributes_json' => $attributes,
                    'status' => 'active',
                ]);

                foreach ($prices as $shopName => $price) {
                    $shop = $this->shops[$shopName];

                    ShopProduct::query()->create([
                        'shop_id' => $shop->getKey(),
                        'variant_id' => $variant->getKey(),
                        'seller_sku' => sprintf('DEMO-%04d', ++$skuNumber),
                        'price_amount' => $price,
                        'currency' => 'BDT',
                        'availability_status' => $skuNumber % 9 === 0 ? 'unavailable' : 'available',
                        'status' => $shop->status === ShopStatus::Active ? 'active' : 'archived',
                    ]);
                }
            }
        }
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
