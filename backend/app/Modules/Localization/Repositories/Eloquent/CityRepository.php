<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Enums\CityType;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Repositories\Contracts\CityRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<City>
 */
class CityRepository extends BaseRepository implements CityRepositoryContract
{
    protected function model(): string
    {
        return City::class;
    }

    public function activeForCountry(int $countryId): Collection
    {
        return $this->query()
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->orderByRaw('case when type = ? then 0 else 1 end', [CityType::Capital->value])
            ->orderBy('id')
            ->get();
    }

    public function upsertMany(array $rows): int
    {
        return $this->query()->upsert($rows, ['country_id', 'slug'], ['name', 'type', 'updated_at']);
    }
}
