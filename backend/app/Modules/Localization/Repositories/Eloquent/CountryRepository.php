<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Country>
 */
class CountryRepository extends BaseRepository implements CountryRepositoryContract
{
    protected function model(): string
    {
        return Country::class;
    }

    public function findByIso2(string $iso2): ?Country
    {
        return $this->query()->where('iso2', strtoupper($iso2))->first();
    }

    public function findActive(int $id): ?Country
    {
        return $this->query()->active()->whereKey($id)->first();
    }

    public function active(?array $ids = null): Collection
    {
        return $this->query()
            ->active()
            ->when($ids !== null, fn ($query) => $query->whereKey($ids ?? []))
            ->orderBy('iso2')
            ->get();
    }

    public function idsByIso2(): array
    {
        /** @var array<string, int> $ids */
        $ids = $this->query()->pluck('id', 'iso2')->map(fn (mixed $id): int => (int) $id)->all();

        return $ids;
    }

    public function upsertMany(array $rows): int
    {
        return $this->query()->upsert($rows, ['iso2'], ['iso3', 'numeric_code', 'name', 'phone_code', 'currency_code', 'flag', 'continent', 'updated_at']);
    }
}
