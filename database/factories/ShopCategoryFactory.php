<?php

namespace Database\Factories;

use App\Modules\MallShopDirectory\Models\ShopCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopCategory>
 */
class ShopCategoryFactory extends Factory
{
    protected $model = ShopCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_category_id' => null,
            'slug' => Str::slug($name),
            'name' => Str::title($name),
        ];
    }

    /**
     * A sub-category of the given parent.
     */
    public function childOf(ShopCategory $parent): static
    {
        return $this->state(fn (array $attributes) => ['parent_category_id' => $parent->getKey()]);
    }
}
