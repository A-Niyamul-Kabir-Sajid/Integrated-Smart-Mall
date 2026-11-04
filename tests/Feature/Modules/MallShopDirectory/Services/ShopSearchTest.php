<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\DirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShopSearchTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $food = ShopCategory::factory()->create(['name' => 'Food & Drinks', 'slug' => 'food']);
        $tech = ShopCategory::factory()->create(['name' => 'Electronics', 'slug' => 'tech']);

        Shop::factory()->for($this->mall)->create(['name' => 'Cafe Brew', 'description' => 'Coffee and fresh pastries.', 'shop_category_id' => $food->getKey(), 'created_at' => now()->subDays(3)]);
        Shop::factory()->for($this->mall)->create(['name' => 'Juice Corner', 'description' => 'Fresh juices, 50% off on Fridays.', 'shop_category_id' => $food->getKey(), 'created_at' => now()->subDays(1)]);
        Shop::factory()->for($this->mall)->create(['name' => 'Gadget Hub', 'description' => 'Phones and chargers.', 'shop_category_id' => $tech->getKey(), 'created_at' => now()->subDays(2)]);
        Shop::factory()->for($this->mall)->draft()->create(['name' => 'Coffee Lab', 'description' => 'Awaiting approval.']);
        Shop::factory()->create(['name' => 'Other Mall Coffee']);
    }

    private function names(array $filters): array
    {
        return app(DirectoryService::class)->browseShops($this->mall, $filters)->pluck('name')->all();
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function searches(): array
    {
        return [
            'name, any letter case' => ['CAFE', ['Cafe Brew']],
            'description' => ['pastries', ['Cafe Brew']],
            'category name' => ['electronics', ['Gadget Hub']],
            'surrounding and repeated spaces' => ['   fresh    juices ', ['Juice Corner']],
            'every word must match' => ['fresh coffee', ['Cafe Brew']],
            'words may match different fields' => ['drinks juice', ['Juice Corner']],
            'percent sign is literal' => ['50%', ['Juice Corner']],
            'lone percent matches only a literal percent sign' => ['%', ['Juice Corner']],
            'underscore is literal' => ['_', []],
            'no match' => ['bicycle', []],
            'drafts and other malls never match' => ['coffee', ['Cafe Brew']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('searches')]
    public function test_finds_listed_shops_of_the_mall_by_keyword(string $search, array $expected): void
    {
        $this->assertSame($expected, $this->names(['q' => $search]));
    }

    public function test_search_combines_with_filters_and_sorting(): void
    {
        $floor = Floor::factory()->for($this->mall)->create(['code' => 'G']);
        Shop::query()->where('name', 'like', '%e%')->get()->each(fn (Shop $shop) => ShopUnit::factory()->for($shop)->for($floor)->create());

        $this->assertSame(['Juice Corner', 'Cafe Brew'], $this->names(['q' => 'fresh', 'category' => 'food', 'floor' => 'G', 'sort' => 'name_desc']));
    }

    public function test_sorts_by_name_either_way_or_by_newest(): void
    {
        $this->assertSame(['Cafe Brew', 'Gadget Hub', 'Juice Corner'], $this->names(['sort' => 'name']));
        $this->assertSame(['Juice Corner', 'Gadget Hub', 'Cafe Brew'], $this->names(['sort' => 'name_desc']));
        $this->assertSame(['Juice Corner', 'Gadget Hub', 'Cafe Brew'], $this->names(['sort' => 'newest']));
    }
}
