<?php

namespace App\Modules\Content\Repositories\Contracts;

use App\Modules\Content\Models\BlogCategory;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<BlogCategory>
 */
interface BlogCategoryRepositoryContract extends RepositoryContract
{
    /**
     * Active categories with their visible posts count (public blog filter).
     *
     * @return Collection<int, BlogCategory>
     */
    public function activeWithCounts(): Collection;

    public function findActiveBySlug(string $slug): ?BlogCategory;

    /**
     * id => name for selects.
     *
     * @return array<int, string>
     */
    public function options(): array;
}
