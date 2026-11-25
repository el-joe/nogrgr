<?php

namespace App\Modules\Content\Repositories\Eloquent;

use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Models\BlogCategory;
use App\Modules\Content\Repositories\Contracts\BlogCategoryRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<BlogCategory>
 */
class BlogCategoryRepository extends BaseRepository implements BlogCategoryRepositoryContract
{
    protected function model(): string
    {
        return BlogCategory::class;
    }

    public function activeWithCounts(): Collection
    {
        return BlogCategory::query()
            ->where('is_active', true)
            ->withCount(['posts' => fn (Builder $query) => $query->where('status', PostStatus::Published->value)->whereNotNull('published_at')->where('published_at', '<=', now())])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findActiveBySlug(string $slug): ?BlogCategory
    {
        return BlogCategory::query()->where('is_active', true)->where('slug', $slug)->first();
    }

    public function options(): array
    {
        return BlogCategory::query()->orderBy('sort_order')->orderBy('id')->get()
            ->mapWithKeys(fn (BlogCategory $category): array => [$category->id => $category->name])
            ->all();
    }
}
