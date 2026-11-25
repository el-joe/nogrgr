<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\Models\DeliveryPopupDay;
use App\Modules\Shipping\Repositories\Contracts\DeliveryPopupDayRepositoryContract;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Delivery popup days (ADM-035): days 1–10, each an integer %, summing to exactly 100.
 * A country without its own distribution uses the "all countries" one; 0 % days are hidden
 * on the storefront.
 */
class DeliveryPopupService
{
    public const DAYS = 10;

    public const TOTAL = 100;

    /** Default distribution (Figma 54:19015) used until the admin saves one. */
    public const DEFAULTS = [1 => 0, 2 => 5, 3 => 20, 4 => 30, 5 => 25, 6 => 10, 7 => 5, 8 => 5, 9 => 0, 10 => 0];

    public function __construct(
        private readonly DeliveryPopupDayRepositoryContract $days,
        private readonly DeliveryEstimateService $estimates,
    ) {}

    /**
     * Full 1..10 distribution for the editor: the country's own, else the all-countries one, else defaults.
     *
     * @return array{percentages: array<int, int>, own: bool}
     */
    public function distribution(?int $countryId = null): array
    {
        $own = $countryId !== null ? $this->days->percentages($countryId) : [];
        $all = $this->days->percentages(DeliveryPopupDay::ALL_COUNTRIES);
        $source = $own !== [] ? $own : ($all !== [] ? $all : self::DEFAULTS);
        $percentages = [];

        for ($day = 1; $day <= self::DAYS; $day++) {
            $percentages[$day] = (int) ($source[$day] ?? 0);
        }

        return ['percentages' => $percentages, 'own' => $countryId === null ? $all !== [] : $own !== []];
    }

    /**
     * @param  array<int, int>  $percentages  day => %
     */
    public function save(?int $countryId, array $percentages): void
    {
        $clean = [];

        for ($day = 1; $day <= self::DAYS; $day++) {
            $clean[$day] = max(0, min(self::TOTAL, (int) ($percentages[$day] ?? 0)));
        }

        if (array_sum($clean) !== self::TOTAL) {
            throw new DomainException(__('shipping::popup.sum_invalid', ['total' => array_sum($clean)]));
        }

        $this->days->replace($countryId ?? DeliveryPopupDay::ALL_COUNTRIES, $clean);
    }

    /**
     * Drops a country's own distribution (it follows "all countries" again) or, for null, resets the default.
     */
    public function reset(?int $countryId): void
    {
        $this->days->clear($countryId ?? DeliveryPopupDay::ALL_COUNTRIES);
    }

    /**
     * @return list<int>
     */
    public function customisedCountryIds(): array
    {
        return $this->days->customisedCountryIds();
    }

    /**
     * Storefront popup rows: only days with a share > 0, each with its delivery date.
     *
     * @return list<array{day: int, percentage: int, date: string}>
     */
    public function forStorefront(int $countryId, ?CarbonInterface $orderedAt = null): array
    {
        $percentages = $this->distribution($countryId)['percentages'];
        $dates = $this->estimates->deliveryDays($countryId, self::DAYS, $orderedAt);
        $rows = [];

        foreach ($percentages as $day => $percentage) {
            if ($percentage > 0) {
                $rows[] = ['day' => $day, 'percentage' => $percentage, 'date' => $dates[$day]->toDateString()];
            }
        }

        return $rows;
    }
}
