<?php

namespace App\Modules\Storefront\Http\Controllers\StoreApi;

use App\Modules\Catalog\Models\Category;
use App\Modules\Storefront\Http\Controllers\StoreApi\Concerns\PreparesCatalogContext;
use App\Modules\Storefront\Http\Resources\StoreApi\CategoryResource;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API categories (APP-006): the storefront tree — central categories the store
 * shows plus its own, in the store's order, hidden central ones left out (CategoryCatalog).
 */
class CategoryController extends Controller
{
    use PreparesCatalogContext;

    public function __construct(private readonly CategoryCatalog $categories) {}

    public function index(Request $request): JsonResponse
    {
        $this->prepareCatalog($request);

        return JsonResponder::success('', [
            'items' => array_map(static fn (array $node): array => (new CategoryResource($node['category'], $node['children']))->toArray($request), $this->categories->tree()),
            'menu_ids' => array_map(static fn (array $node): int => $node['category']->id, $this->categories->menu()),
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $this->prepareCatalog($request);
        $category = $this->categories->findBySlug($slug);

        if ($category === null) {
            return JsonResponder::error((string) __('api::store.category_not_found'), 404);
        }

        return JsonResponder::success('', [
            'category' => (new CategoryResource($category, $this->categories->children($category)))->toArray($request),
            'breadcrumbs' => array_map(static fn (Category $node): array => ['id' => $node->id, 'name' => (string) $node->name, 'slug' => $node->slug], $this->categories->ancestry($category)),
        ]);
    }
}
