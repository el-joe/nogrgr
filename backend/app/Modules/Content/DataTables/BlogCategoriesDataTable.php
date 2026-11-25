<?php

namespace App\Modules\Content\DataTables;

use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Repositories\Contracts\BlogCategoryRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin blog categories list (ADM-080).
 *
 * @extends BaseDataTable<BlogCategory>
 */
final class BlogCategoriesDataTable extends BaseDataTable
{
    public function __construct(private readonly BlogCategoryRepositoryContract $categories) {}

    protected function query(): Builder
    {
        return $this->categories->query()->withCount('posts');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? null;

        return $query->when(is_string($search) && $search !== '', fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('name', fn (BlogCategory $category): string => '<span class="font-medium text-ink">'.e($category->name).'</span><span class="block text-xs text-ink-muted" dir="ltr">'.e($category->slug).'</span>')
            ->editColumn('posts_count', fn (BlogCategory $category): string => e((string) ($category->posts_count ?? 0)))
            ->editColumn('is_active', fn (BlogCategory $category): string => view('content::admin._badge', ['tone' => $category->is_active ? 'success' : 'neutral', 'label' => $category->is_active ? __('content::admin.common.yes') : __('content::admin.common.no')])->render())
            ->addColumn('actions', fn (BlogCategory $category): string => view('content::admin.post-categories._actions', ['category' => $category])->render())
            ->rawColumns(['name', 'is_active', 'actions']);
    }
}
