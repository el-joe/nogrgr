<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Inventory\Models\Branch;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Branch>
 */
interface BranchRepositoryContract extends RepositoryContract
{
    /**
     * Active branches, by name.
     *
     * @return Collection<int, Branch>
     */
    public function active(): Collection;

    /**
     * @return array{active: int, all: int}
     */
    public function counts(): array;

    /**
     * Makes the branch the default for exactly these countries (taking them from other branches).
     *
     * @param  list<int>  $countryIds
     */
    public function syncDefaultCountries(Branch $branch, array $countryIds): void;

    /**
     * @param  list<int>  $cityIds
     */
    public function syncZones(Branch $branch, array $cityIds): void;
}
