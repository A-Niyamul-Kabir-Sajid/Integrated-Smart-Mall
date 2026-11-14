<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Services\ShopCategoryService;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Maintains the shop category tree, which every mall shares, so only platform operators may
 * change it (Jira ISM-110, decision D-015). Mall administrators choose from it when they set up a shop.
 */
class CategoryAdministrationService
{
    public function __construct(
        private ShopCategoryService $categories,
        private AuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, ShopCategory>
     */
    public function all(): Collection
    {
        return ShopCategory::query()->with('parent:shop_category_id,name')->withCount('shops')->orderBy('name')->get();
    }

    public function create(User $operator, string $slug, string $name, ?ShopCategory $parent = null): ShopCategory
    {
        Gate::forUser($operator)->authorize(Ability::AdministerPlatform->value);

        $category = $this->categories->create($slug, $name, $parent);
        $this->audit->record('shop_category.created', $category, ['slug' => $category->slug]);

        return $category;
    }

    public function update(User $operator, ShopCategory $category, string $name, ?ShopCategory $parent): ShopCategory
    {
        Gate::forUser($operator)->authorize(Ability::AdministerPlatform->value);

        DB::transaction(function () use ($category, $name, $parent): void {
            $this->categories->rename($category, $name);
            $this->categories->moveUnder($category, $parent);
            $this->audit->record('shop_category.updated', $category, ['fields' => array_keys($category->getChanges())]);
        });

        return $category;
    }
}
