<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Models\ThemeCategory;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Theme>
 */
class ThemeRepository extends BaseRepository implements ThemeRepositoryContract
{
    protected function model(): string
    {
        return Theme::class;
    }

    public function gallery(?ThemeCategory $category = null, ?int $limit = null): Collection
    {
        return Theme::query()
            ->with('categories')
            ->where('is_active', true)
            ->when($category !== null, fn (Builder $query) => $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($category?->id)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->when($limit !== null, fn (Builder $query) => $query->limit((int) $limit))
            ->get();
    }

    public function categories(): Collection
    {
        return ThemeCategory::query()
            ->withCount(['themes' => fn (Builder $query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ThemeCategory $category): bool => ($category->themes_count ?? 0) > 0)
            ->values();
    }

    public function findCategoryBySlug(string $slug): ?ThemeCategory
    {
        return ThemeCategory::query()->where('slug', $slug)->first();
    }
}
