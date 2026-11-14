<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Services\CategoryAdministrationService;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The shop category tree shared by every mall. Routes require the platform administrator.
 */
class CategoryAdminController extends Controller
{
    public function index(CategoryAdministrationService $categories): View
    {
        return view('mall-admin.categories.index', ['categories' => $categories->all()]);
    }

    public function store(Request $request, CategoryAdministrationService $categories): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:80'],
            'parent_category_id' => ['nullable', 'uuid'],
        ]);

        $category = $categories->create($request->user(), $data['slug'], $data['name'], filled($data['parent_category_id'] ?? null) ? ShopCategory::query()->findOrFail($data['parent_category_id']) : null);

        return back()->with('status', __('Category :name was added.', ['name' => $category->name]));
    }

    public function update(Request $request, ShopCategory $category, CategoryAdministrationService $categories): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_category_id' => ['nullable', 'uuid'],
        ]);

        $categories->update($request->user(), $category, $data['name'], filled($data['parent_category_id'] ?? null) ? ShopCategory::query()->findOrFail($data['parent_category_id']) : null);

        return back()->with('status', __('Category :name was saved.', ['name' => $category->name]));
    }
}
