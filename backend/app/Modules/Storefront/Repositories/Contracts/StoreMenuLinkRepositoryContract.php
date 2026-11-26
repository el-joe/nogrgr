<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Enums\MenuLinkPlacement;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store menu links (tenant DB, VEN-034d/e).
 *
 * @extends RepositoryContract<StoreMenuLink>
 */
interface StoreMenuLinkRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, StoreMenuLink> in sort order
     */
    public function forPlacement(MenuLinkPlacement $placement, bool $activeOnly = false): Collection;

    public function nextSortOrder(MenuLinkPlacement $placement): int;

    /**
     * @param  list<int>  $ids  new order of the placement's links
     */
    public function reorder(MenuLinkPlacement $placement, array $ids): void;
}
