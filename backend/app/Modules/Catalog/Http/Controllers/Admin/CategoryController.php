<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\DTOs\CategoryData;
use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Admin\CategoryRequest;
use App\Modules\Catalog\Http\Requests\Admin\CategoryTreeRequest;
use App\Modules\Catalog\Http\Requests\Admin\ToggleRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Catalog › Categories (ADM-014 / 014a, Figma 65:22996 / 65:23866): drag-and-drop
 * tree with a detail panel, featured switch, delete, and the create / edit form.
 */
class CategoryController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly CategoryService $categories,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(): View
    {
        return view('catalog::admin.categories.index', [
            'tree' => $this->categories->tree(),
            'total' => $this->categories->count(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Category);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categories->create(CategoryData::fromArray($request->payload()));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('catalog::categories.created', ['name' => $category->name]), redirect: route('admin.catalog.categories.index'));
    }

    public function edit(Category $category): View
    {
        return $this->form($category);
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        return $this->attempt(
            fn () => $this->categories->update($category, CategoryData::fromArray($request->payload())),
            __('catalog::categories.updated', ['name' => $category->name]),
            route('admin.catalog.categories.index'),
        );
    }

    public function featured(ToggleRequest $request, Category $category): JsonResponse
    {
        $this->categories->setFeatured($category, $request->value());

        return JsonResponder::success(__($request->value() ? 'catalog::categories.featured_on' : 'catalog::categories.featured_off', ['name' => $category->name]));
    }

    public function destroy(Category $category): JsonResponse
    {
        return $this->attempt(
            fn () => $this->categories->delete($category),
            __('catalog::categories.deleted', ['name' => $category->name]),
            route('admin.catalog.categories.index'),
        );
    }

    public function saveTree(CategoryTreeRequest $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->categories->saveTree($request->tree()),
            __('catalog::categories.tree_saved'),
        );
    }

    private function form(Category $category): View
    {
        return view('catalog::admin.categories.form', [
            'category' => $category,
            'parents' => $this->categories->parentOptions($category->exists ? $category : null),
            'locales' => $this->locales->all(),
        ]);
    }
}
