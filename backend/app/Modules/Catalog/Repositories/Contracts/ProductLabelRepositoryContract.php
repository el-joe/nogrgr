<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\ProductLabel;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<ProductLabel>
 */
interface ProductLabelRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, ProductLabel>
     */
    public function ordered(): Collection;

    public function nextSortOrder(): int;

    /**
     * @return list<int>
     */
    public function ids(): array;

    /**
     * @param  list<int>  $ids
     */
    public function saveOrder(array $ids): void;
}
