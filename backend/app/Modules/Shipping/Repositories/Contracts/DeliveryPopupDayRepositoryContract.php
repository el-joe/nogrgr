<?php

namespace App\Modules\Shipping\Repositories\Contracts;

use App\Modules\Shipping\Models\DeliveryPopupDay;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<DeliveryPopupDay>
 */
interface DeliveryPopupDayRepositoryContract extends RepositoryContract
{
    /**
     * day_number => percentage of one country key (0 = all countries); empty when none saved.
     *
     * @return array<int, int>
     */
    public function percentages(int $countryKey): array;

    /**
     * @param  array<int, int>  $percentages  day_number => percentage (1..10)
     */
    public function replace(int $countryKey, array $percentages): void;

    public function clear(int $countryKey): void;

    /**
     * @return list<int> country ids with their own distribution
     */
    public function customisedCountryIds(): array;
}
