<?php

namespace App\Modules\Content\Repositories\Contracts;

use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Models\BlogPost;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<BlogPost>
 */
interface BlogPostRepositoryContract extends RepositoryContract
{
    /**
     * Published, visible posts for the public blog, newest first.
     *
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function publicPaginate(?BlogCategory $category, ?string $search, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, BlogPost>
     */
    public function latestVisible(int $limit): Collection;

    public function findVisibleBySlug(string $slug): ?BlogPost;

    /**
     * @return Collection<int, BlogPost>
     */
    public function related(BlogPost $post, int $limit): Collection;

    /**
     * @return Collection<int, BlogPost>
     */
    public function allVisible(): Collection;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;
}
