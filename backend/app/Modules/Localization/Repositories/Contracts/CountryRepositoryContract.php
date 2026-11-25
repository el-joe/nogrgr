<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\Country;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Country>
 */
interface CountryRepositoryContract extends RepositoryContract
{
    public function findByIso2(string $iso2): ?Country;

    public function findActive(int $id): ?Country;

    /**
     * Active countries, optionally limited to the given ids.
     *
     * @param  list<int>|null  $ids
     * @return Collection<int, Country>
     */
    public function active(?array $ids = null): Collection;

    /**
     * @return array<string, int> iso2 => id
     */
    public function idsByIso2(): array;

    /**
     * Insert or update seed rows (`is_active` is only set on insert) (`name` already JSON encoded).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int;
}
