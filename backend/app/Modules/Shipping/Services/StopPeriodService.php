<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\DTOs\StopPeriodData;
use App\Modules\Shipping\Enums\StopPeriodRecurrence;
use App\Modules\Shipping\Models\ShippingStopPeriod;
use App\Modules\Shipping\Repositories\Contracts\ShippingStopPeriodRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Stop periods (ADM-033c): CRUD and the month calendar (date => periods covering it).
 */
class StopPeriodService
{
    public function __construct(private readonly ShippingStopPeriodRepositoryContract $periods) {}

    /**
     * @return Collection<int, ShippingStopPeriod>
     */
    public function listing(?int $companyId = null, ?int $countryId = null): Collection
    {
        return $this->periods->listing($companyId, $countryId);
    }

    public function create(StopPeriodData $data): ShippingStopPeriod
    {
        return $this->periods->create($this->columns($data));
    }

    public function update(ShippingStopPeriod $period, StopPeriodData $data): ShippingStopPeriod
    {
        $this->periods->update($period, $this->columns($data));

        return $period;
    }

    public function delete(ShippingStopPeriod $period): void
    {
        $this->periods->delete($period);
    }

    /**
     * Calendar weeks of a month (Sunday first): each cell {date|null, periods}.
     *
     * @param  Collection<int, ShippingStopPeriod>  $periods
     * @return list<list<array{date: CarbonImmutable|null, periods: list<ShippingStopPeriod>}>>
     */
    public function calendar(CarbonImmutable $month, Collection $periods): array
    {
        $first = $month->startOfMonth();
        $cells = array_fill(0, $first->dayOfWeek, ['date' => null, 'periods' => []]);

        for ($date = $first; $date->month === $first->month; $date = $date->addDay()) {
            $cells[] = [
                'date' => $date,
                'periods' => $periods->filter(static fn (ShippingStopPeriod $period): bool => $period->is_active && $period->toRule()->covers($date))->values()->all(),
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = ['date' => null, 'periods' => []];
        }

        return array_chunk($cells, 7);
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(StopPeriodData $data): array
    {
        $weekly = $data->recurrence === StopPeriodRecurrence::Weekly;

        return [
            'name' => $data->name,
            'shipping_company_id' => $data->shippingCompanyId,
            'country_id' => $data->countryId,
            'recurrence' => $data->recurrence,
            'starts_on' => $data->startsOn,
            'ends_on' => $data->endsOn,
            'weekday' => $weekly ? $data->weekday : null,
            'is_active' => $data->isActive,
        ];
    }
}
