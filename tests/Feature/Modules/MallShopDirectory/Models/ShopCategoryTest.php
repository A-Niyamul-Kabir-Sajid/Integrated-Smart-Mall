<?php

namespace Tests\Feature\Modules\MallShopDirectory\Models;

use App\Modules\MallShopDirectory\Models\ShopCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_level_scope_lists_only_categories_without_a_parent(): void
    {
        $food = ShopCategory::factory()->create(['name' => 'Food & Drinks']);
        ShopCategory::factory()->childOf($food)->create(['name' => 'Cafes']);

        $this->assertSame(['Food & Drinks'], ShopCategory::query()->topLevel()->pluck('name')->all());
    }

    public function test_children_are_listed_alphabetically(): void
    {
        $food = ShopCategory::factory()->create();
        ShopCategory::factory()->childOf($food)->create(['name' => 'Restaurants']);
        ShopCategory::factory()->childOf($food)->create(['name' => 'Cafes']);

        $this->assertSame(['Cafes', 'Restaurants'], $food->children()->pluck('name')->all());
        $this->assertTrue($food->children()->first()->parent->is($food));
    }

    public function test_slugs_are_unique(): void
    {
        ShopCategory::factory()->create(['slug' => 'fashion']);

        $this->expectException(QueryException::class);

        ShopCategory::factory()->create(['slug' => 'fashion']);
    }
}
