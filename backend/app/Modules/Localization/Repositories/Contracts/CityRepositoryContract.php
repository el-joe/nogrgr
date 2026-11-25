<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\City;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<City>
 */
interface CityRepositoryContract extends RepositoryContract
{
    /**
     * Active cities of a country, capitals first.
     *
     * @return Collection<int, City>
     */
    public function activeForCountry(int $countryId): Collection;

    /**
     * Insert or update seed rows (`name` already JSON encoded; `is_active` is only set on insert).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int;
}
