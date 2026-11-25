<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Admin\SortRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CategoryService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Catalog › Categories › Sort (ADM-014b / 014c, Figma 65:24654): "Category order"
 * tab (whole tree) and "Products in category" tab (product order per category).
 */
class CategorySortController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(private readonly CategoryService $categories) {}

    public function categories(): View
    {
        return view('catalog::admin.categories.sort', [
            'tab' => 'categories',
            'tree' => $this->categories->tree(),
            'flat' => $this->categories->flatten(),
            'current' => null,
            'products' => collect(),
        ]);
    }

    public function products(Category $category): View
    {
        return view('catalog::admin.categories.sort', [
            'tab' => 'products',
            'tree' => [],
            'flat' => $this->categories->flatten(),
            'current' => $category,
            'products' => $this->categories->products($category),
        ]);
    }

    public function saveProducts(SortRequest $request, Category $category): JsonResponse
    {
        return $this->attempt(
            fn () => $this->categories->saveProductOrder($category, $request->ids()),
            __('catalog::categories.products_order_saved', ['name' => $category->name]),
        );
    }
}
