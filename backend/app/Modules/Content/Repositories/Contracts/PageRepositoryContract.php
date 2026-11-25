<?php

namespace App\Modules\Content\Repositories\Contracts;

use App\Modules\Content\Models\Page;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Page>
 */
interface PageRepositoryContract extends RepositoryContract
{
    /**
     * An active page shown on the central website (target central or both).
     */
    public function findPublicBySlug(string $slug): ?Page;

    /**
     * @return Collection<int, Page>
     */
    public function publicPages(): Collection;
}
