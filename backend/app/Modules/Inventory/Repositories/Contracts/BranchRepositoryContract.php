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
     * Active branch that is the default of the country, else of any country (lowest code).
     */
    public function activeDefault(?int $countryId): ?Branch;

    /**
     * Makes the branch the default of its own country when that country has none yet.
     */
    public function markDefaultIfMissing(Branch $branch): void;

    /**
     * A branch code not used yet (soft-deleted branches included): `$base`, `$base-2`, …
     */
    public function freeCode(string $base): string;

    /**
     * Country of new default records: config('app.default_country') when active, else the
     * first active country; null when no country exists yet.
     */
    public function platformCountryId(): ?int;

    /**
     * @param  list<int>  $zoneIds  shipping zone ids
     */
    public function syncZones(Branch $branch, array $zoneIds): void;
}
