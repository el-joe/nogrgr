<?php

namespace App\Modules\Shipping\Repositories\Contracts;

use App\Modules\Shipping\Models\ShippingStopPeriod;
use App\Modules\Shipping\Support\StopPeriodRule;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<ShippingStopPeriod>
 */
interface ShippingStopPeriodRepositoryContract extends RepositoryContract
{
    /**
     * Active stop periods applying to a company and country (rows with null = all match).
     *
     * @return list<StopPeriodRule>
     */
    public function rulesFor(?int $companyId, ?int $countryId): array;

    /**
     * Periods for the calendar / list, optionally filtered (null filter = every row).
     *
     * @return Collection<int, ShippingStopPeriod>
     */
    public function listing(?int $companyId = null, ?int $countryId = null): Collection;
}
