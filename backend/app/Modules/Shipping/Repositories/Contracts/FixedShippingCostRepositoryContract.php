<?php

namespace App\Modules\Shipping\Repositories\Contracts;

use App\Modules\Shipping\Models\FixedShippingCost;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<FixedShippingCost>
 */
interface FixedShippingCostRepositoryContract extends RepositoryContract
{
    /**
     * {country_id: price_per_gram} of every configured country.
     *
     * @return array<int, string>
     */
    public function ratesByCountry(): array;

    public function forCountry(int $countryId): ?FixedShippingCost;
}
