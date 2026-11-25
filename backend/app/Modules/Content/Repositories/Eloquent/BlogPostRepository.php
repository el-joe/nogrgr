<?php

namespace App\Modules\Content\Repositories\Eloquent;

use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<BlogPost>
 */
class BlogPostRepository extends BaseRepository implements BlogPostRepositoryContract
{
    protected function model(): string
    {
        return BlogPost::class;
    }

    public function query(): Builder
    {
        return BlogPost::query()->with('category');
    }

    public function publicPaginate(?BlogCategory $category, ?string $search, int $perPage): LengthAwarePaginator
    {
        $term = $search !== null && trim($search) !== '' ? '%'.addcslashes(trim($search), '%_\\').'%' : null;

        return $this->visibleQuery()
            ->when($category !== null, fn (Builder $query) => $query->where('blog_category_id', $category?->id))
            ->when($term !== null, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('title', 'like', $term)->orWhere('excerpt', 'like', $term)))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function latestVisible(int $limit): Collection
    {
        return $this->visibleQuery()->limit($limit)->get();
    }

    public function findVisibleBySlug(string $slug): ?BlogPost
    {
        return $this->visibleQuery()->where('slug', $slug)->first();
    }

    public function related(BlogPost $post, int $limit): Collection
    {
        $sameCategory = $post->blog_category_id === null ? new Collection : $this->visibleQuery()
            ->whereKeyNot($post->id)
            ->where('blog_category_id', $post->blog_category_id)
            ->limit($limit)
            ->get();

        if ($sameCategory->count() >= $limit) {
            return $sameCategory;
        }

        return $sameCategory->concat(
            $this->visibleQuery()->whereKeyNot([$post->id, ...$sameCategory->modelKeys()])->limit($limit - $sameCategory->count())->get()
        );
    }

    public function allVisible(): Collection
    {
        return $this->visibleQuery()->get();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return BlogPost::query()->where('slug', $slug)->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists();
    }

    /**
     * @return Builder<BlogPost>
     */
    private function visibleQuery(): Builder
    {
        return BlogPost::query()->with('category')->visible()->orderByDesc('published_at')->orderByDesc('id');
    }
}
