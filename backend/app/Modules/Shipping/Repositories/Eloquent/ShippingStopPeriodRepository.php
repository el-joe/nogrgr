<?php

namespace App\Modules\Shipping\Repositories\Eloquent;

use App\Modules\Shipping\Models\ShippingStopPeriod;
use App\Modules\Shipping\Repositories\Contracts\ShippingStopPeriodRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ShippingStopPeriod>
 */
class ShippingStopPeriodRepository extends BaseRepository implements ShippingStopPeriodRepositoryContract
{
    protected function model(): string
    {
        return ShippingStopPeriod::class;
    }

    public function rulesFor(?int $companyId, ?int $countryId): array
    {
        return $this->query()
            ->where('is_active', true)
            ->where(static fn (Builder $query) => $query->whereNull('shipping_company_id')->when($companyId !== null, static fn (Builder $query) => $query->orWhere('shipping_company_id', $companyId)))
            ->where(static fn (Builder $query) => $query->whereNull('country_id')->when($countryId !== null, static fn (Builder $query) => $query->orWhere('country_id', $countryId)))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->map(static fn (ShippingStopPeriod $period) => $period->toRule())
            ->values()
            ->all();
    }

    public function listing(?int $companyId = null, ?int $countryId = null): Collection
    {
        return $this->query()
            ->with(['company', 'country'])
            ->when($companyId !== null, static fn (Builder $query) => $query->where(static fn (Builder $query) => $query->whereNull('shipping_company_id')->orWhere('shipping_company_id', $companyId)))
            ->when($countryId !== null, static fn (Builder $query) => $query->where(static fn (Builder $query) => $query->whereNull('country_id')->orWhere('country_id', $countryId)))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();
    }
}
