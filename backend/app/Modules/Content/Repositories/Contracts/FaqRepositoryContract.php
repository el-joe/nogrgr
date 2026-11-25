<?php

namespace App\Modules\Content\Repositories\Contracts;

use App\Modules\Content\Models\Faq;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Faq>
 */
interface FaqRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, Faq>
     */
    public function ordered(bool $activeOnly = false): Collection;

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids): void;

    public function nextSortOrder(): int;
}
