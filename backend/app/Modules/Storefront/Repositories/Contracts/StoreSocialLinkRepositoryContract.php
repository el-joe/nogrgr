<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Models\StoreSocialLink;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store social profiles (tenant DB, VEN-034f).
 *
 * @extends RepositoryContract<StoreSocialLink>
 */
interface StoreSocialLinkRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, StoreSocialLink> in sort order
     */
    public function ordered(bool $activeOnly = false): Collection;

    /**
     * @return list<string> networks already linked
     */
    public function usedNetworks(): array;

    public function nextSortOrder(): int;
}
