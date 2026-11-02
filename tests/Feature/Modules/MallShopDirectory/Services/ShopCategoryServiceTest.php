<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Services\ShopCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function assertRuleViolated(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }

    public function test_creates_top_level_and_nested_categories(): void
    {
        $service = app(ShopCategoryService::class);

        $food = $service->create('food-drinks', 'Food & Drinks');
        $cafes = $service->create('cafes', ' Cafes ', $food);

        $this->assertNull($food->parent_category_id);
        $this->assertSame('Cafes', $cafes->name);
        $this->assertTrue($cafes->parent->is($food));
    }

    public function test_rejects_malformed_or_duplicate_slugs(): void
    {
        $service = app(ShopCategoryService::class);
        $service->create('fashion', 'Fashion');

        $this->assertRuleViolated('shop_category.invalid_slug', fn () => $service->create('Food Court', 'Food'));
        $this->assertRuleViolated('shop_category.slug_taken', fn () => $service->create('fashion', 'Clothing'));
    }

    public function test_a_category_cannot_become_its_own_parent(): void
    {
        $food = ShopCategory::factory()->create();

        $this->assertRuleViolated('shop_category.cycle', fn () => app(ShopCategoryService::class)->moveUnder($food, $food));
    }

    public function test_a_category_cannot_move_under_its_own_descendant(): void
    {
        $food = ShopCategory::factory()->create();
        $cafes = ShopCategory::factory()->childOf($food)->create();
        $coffee = ShopCategory::factory()->childOf($cafes)->create();

        $this->assertRuleViolated('shop_category.cycle', fn () => app(ShopCategoryService::class)->moveUnder($food, $coffee));
        $this->assertNull($food->fresh()->parent_category_id);
    }

    public function test_a_category_can_move_to_another_branch_or_to_the_top_level(): void
    {
        $food = ShopCategory::factory()->create();
        $services = ShopCategory::factory()->create();
        $cafes = ShopCategory::factory()->childOf($food)->create();
        $service = app(ShopCategoryService::class);

        $service->moveUnder($cafes, $services);
        $this->assertSame($services->getKey(), $cafes->fresh()->parent_category_id);

        $service->moveUnder($cafes, null);
        $this->assertNull($cafes->fresh()->parent_category_id);
    }
}
