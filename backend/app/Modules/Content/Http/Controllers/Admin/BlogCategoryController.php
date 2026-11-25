<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Modules\Content\DataTables\BlogCategoriesDataTable;
use App\Modules\Content\Http\Requests\Admin\BlogCategoryRequest;
use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Services\BlogCategoryService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Blog categories (ADM-080 list, ADM-080a create / edit). Permissions:
 * content.post-categories.view / .manage.
 */
class BlogCategoryController extends Controller
{
    public function __construct(private readonly BlogCategoryService $service) {}

    public function index(): View
    {
        return view('content::admin.post-categories.index');
    }

    public function data(Request $request, BlogCategoriesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ['search']);
    }

    public function create(): View
    {
        return view('content::admin.post-categories.form', ['category' => new BlogCategory(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(BlogCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create($request->toData());

        return JsonResponder::created(__('content::admin.common.created'), ['id' => $category->id], route('admin.content.post-categories.index'));
    }

    public function edit(BlogCategory $category): View
    {
        return view('content::admin.post-categories.form', ['category' => $category]);
    }

    public function update(BlogCategoryRequest $request, BlogCategory $category): JsonResponse
    {
        $this->service->update($category, $request->toData());

        return JsonResponder::success(__('content::admin.common.saved'), null, route('admin.content.post-categories.index'));
    }

    public function destroy(BlogCategory $category): JsonResponse
    {
        $this->service->delete($category);

        return JsonResponder::success(__('content::admin.common.deleted'));
    }
}
