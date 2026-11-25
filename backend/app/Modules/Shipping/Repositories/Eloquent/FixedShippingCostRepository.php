<?php

namespace App\Modules\Shipping\Repositories\Eloquent;

use App\Modules\Shipping\Models\FixedShippingCost;
use App\Modules\Shipping\Repositories\Contracts\FixedShippingCostRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<FixedShippingCost>
 */
class FixedShippingCostRepository extends BaseRepository implements FixedShippingCostRepositoryContract
{
    protected function model(): string
    {
        return FixedShippingCost::class;
    }

    public function ratesByCountry(): array
    {
        /** @var array<int, string> $rates */
        $rates = $this->query()->orderBy('country_id')->pluck('price_per_gram', 'country_id')->map(static fn ($rate): string => (string) $rate)->all();

        return $rates;
    }

    public function forCountry(int $countryId): ?FixedShippingCost
    {
        return $this->query()->where('country_id', $countryId)->first();
    }
}
