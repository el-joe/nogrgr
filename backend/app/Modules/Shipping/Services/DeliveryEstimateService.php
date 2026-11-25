<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\DTOs\DeliveryEstimate;
use App\Modules\Shipping\Repositories\Contracts\ShippingCompanyRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ShippingStopPeriodRepositoryContract;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves the inputs of the pure DeliveryDateEstimator for a destination:
 *   company  = the given one, else the country's default company
 *   days     = that company's day rule for the country, else the settings defaults
 *   stops    = active stop periods of the company / all companies, the country / all countries
 *   off days + cut-off from the shipping settings.
 * Used by the admin estimator preview, labels, product page and checkout (P5).
 */
class DeliveryEstimateService
{
    public function __construct(
        private readonly DeliveryDateEstimator $estimator,
        private readonly ShippingCompanyRepositoryContract $companies,
        private readonly ShippingStopPeriodRepositoryContract $stops,
        private readonly ShippingSettingsService $settings,
    ) {}

    public function estimateFor(int $countryId, ?int $companyId = null, ?CarbonInterface $orderedAt = null): DeliveryEstimate
    {
        $settings = $this->settings->get();
        $companyId ??= $this->companies->defaultCompanyId($countryId);
        $rule = $companyId !== null ? $this->companies->dayRule($companyId, $countryId) : null;
        $minDays = $rule->min_days ?? $settings->defaultMinDays;
        $maxDays = max($minDays, $rule->max_days ?? $settings->defaultMaxDays);

        $estimate = $this->estimator->estimate(
            $orderedAt ?? CarbonImmutable::now(),
            $minDays,
            $maxDays,
            $this->stops->rulesFor($companyId, $countryId),
            $settings->weeklyOffDays,
            $settings->cutoffTime,
        );

        $company = $companyId !== null ? $this->companies->find($companyId) : null;

        return new DeliveryEstimate(
            $estimate->startDate,
            $estimate->minDate,
            $estimate->maxDate,
            $estimate->minDays,
            $estimate->maxDays,
            $estimate->skipped,
            $companyId,
            $company?->getAttribute('name'),
        );
    }

    /**
     * Dates of delivery days 1..$count for a destination (delivery popup).
     *
     * @return array<int, CarbonImmutable>
     */
    public function deliveryDays(int $countryId, int $count, ?CarbonInterface $orderedAt = null): array
    {
        $settings = $this->settings->get();
        $companyId = $this->companies->defaultCompanyId($countryId);

        return $this->estimator->deliveryDays(
            $orderedAt ?? CarbonImmutable::now(),
            $count,
            $this->stops->rulesFor($companyId, $countryId),
            $settings->weeklyOffDays,
            $settings->cutoffTime,
        );
    }
}
