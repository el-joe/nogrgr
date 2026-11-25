<?php

namespace App\Modules\Content\DataTables;

use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin blog posts list (ADM-081): filter status / category, search title.
 *
 * @extends BaseDataTable<BlogPost>
 */
final class BlogPostsDataTable extends BaseDataTable
{
    public function __construct(private readonly BlogPostRepositoryContract $posts) {}

    protected function query(): Builder
    {
        return $this->posts->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $category = $filters['category'] ?? null;
        $search = $filters['search'] ?? null;

        return $query
            ->when(is_string($status) && PostStatus::tryFrom($status) !== null, fn (Builder $q) => $q->where('status', $status))
            ->when(is_numeric($category), fn (Builder $q) => $q->where('blog_category_id', (int) $category))
            ->when(is_string($search) && $search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('title', 'like', '%'.addcslashes((string) $search, '%_\\').'%')->orWhere('slug', 'like', '%'.addcslashes((string) $search, '%_\\').'%')));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('title', fn (BlogPost $post): string => view('content::admin.posts._title-cell', ['post' => $post])->render())
            ->addColumn('category', fn (BlogPost $post): string => e($post->category->name ?? '—'))
            ->editColumn('status', fn (BlogPost $post): string => view('content::admin.posts._status', ['post' => $post])->render())
            ->editColumn('published_at', fn (BlogPost $post): string => $post->published_at !== null ? '<bdi dir="ltr">'.e($post->published_at->format('Y-m-d H:i')).'</bdi>' : '—')
            ->addColumn('actions', fn (BlogPost $post): string => view('content::admin.posts._actions', ['post' => $post])->render())
            ->rawColumns(['title', 'status', 'published_at', 'actions']);
    }
}
