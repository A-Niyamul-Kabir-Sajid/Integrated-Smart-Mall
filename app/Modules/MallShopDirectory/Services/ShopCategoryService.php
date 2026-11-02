<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\ShopCategory;

/**
 * Maintains the shop category tree. The parent chain must stay acyclic.
 */
class ShopCategoryService
{
    public function create(string $slug, string $name, ?ShopCategory $parent = null): ShopCategory
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new BusinessRuleViolation('shop_category.invalid_slug', __('A category slug may only contain lower-case letters, digits and single hyphens.'));
        }

        if (ShopCategory::query()->where('slug', $slug)->exists()) {
            throw new BusinessRuleViolation('shop_category.slug_taken', __('Another category already uses this slug.'));
        }

        return ShopCategory::query()->create([
            'slug' => $slug,
            'name' => trim($name),
            'parent_category_id' => $parent?->getKey(),
        ]);
    }

    /**
     * Moves a category under a new parent, or to the top level with null.
     */
    public function moveUnder(ShopCategory $category, ?ShopCategory $parent): ShopCategory
    {
        if ($parent !== null && $this->wouldCreateCycle($category, $parent)) {
            throw new BusinessRuleViolation('shop_category.cycle', __('A category cannot be placed under itself or one of its sub-categories.'));
        }

        $category->update(['parent_category_id' => $parent?->getKey()]);

        return $category;
    }

    private function wouldCreateCycle(ShopCategory $category, ShopCategory $parent): bool
    {
        $ancestorId = $parent->getKey();
        $visited = [];

        while ($ancestorId !== null && ! isset($visited[$ancestorId])) {
            if ($ancestorId === $category->getKey()) {
                return true;
            }

            $visited[$ancestorId] = true;
            $ancestorId = ShopCategory::query()->whereKey($ancestorId)->value('parent_category_id');
        }

        return false;
    }
}
