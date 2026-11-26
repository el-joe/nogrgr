<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Models\ThemeCategory;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Theme>
 */
interface ThemeRepositoryContract extends RepositoryContract
{
    /**
     * Active themes (with categories) for the central gallery, optionally in one category.
     *
     * @return Collection<int, Theme>
     */
    public function gallery(?ThemeCategory $category = null, ?int $limit = null): Collection;

    /**
     * Categories with at least one active theme, with themes_count.
     *
     * @return Collection<int, ThemeCategory>
     */
    public function categories(): Collection;

    public function findCategoryBySlug(string $slug): ?ThemeCategory;

    public function findActiveBySlug(string $slug): ?Theme;

    /**
     * Active themes of the catalogue in display order (vendor theme picker, resolution fallback).
     *
     * @return Collection<int, Theme>
     */
    public function active(): Collection;
}
