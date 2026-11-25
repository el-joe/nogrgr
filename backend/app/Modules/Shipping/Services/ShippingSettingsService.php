<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\DTOs\ShippingSettingsData;
use App\Modules\Shipping\Repositories\Contracts\ShippingCompanyRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ShippingSettingRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Shipping settings (ADM-034): typed read with defaults, save, default company per country.
 */
class ShippingSettingsService extends BaseService
{
    private ?ShippingSettingsData $memo = null;

    public function __construct(
        private readonly ShippingSettingRepositoryContract $settings,
        private readonly ShippingCompanyRepositoryContract $companies,
    ) {}

    public function get(): ShippingSettingsData
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $stored = $this->settings->values();
        $defaults = new ShippingSettingsData;

        return $this->memo = new ShippingSettingsData(
            defaultMinDays: (int) ($stored['default_min_days'] ?? $defaults->defaultMinDays),
            defaultMaxDays: (int) ($stored['default_max_days'] ?? $defaults->defaultMaxDays),
            cutoffTime: isset($stored['cutoff_time']) && is_string($stored['cutoff_time']) ? $stored['cutoff_time'] : null,
            weeklyOffDays: array_values(array_map('intval', (array) ($stored['weekly_off_days'] ?? []))),
            showEstimateOnProduct: (bool) ($stored['show_estimate_on_product'] ?? $defaults->showEstimateOnProduct),
            showDeliveryPopup: (bool) ($stored['show_delivery_popup'] ?? $defaults->showDeliveryPopup),
        );
    }

    public function update(ShippingSettingsData $data): void
    {
        $this->settings->put([
            'default_min_days' => $data->defaultMinDays,
            'default_max_days' => $data->defaultMaxDays,
            'cutoff_time' => $data->cutoffTime,
            'weekly_off_days' => array_values(array_unique($data->weeklyOffDays)),
            'show_estimate_on_product' => $data->showEstimateOnProduct,
            'show_delivery_popup' => $data->showDeliveryPopup,
        ]);
        $this->memo = null;
    }

    /**
     * @return list<array{country_id: int, company_id: int, company: string, min_days: int|null, max_days: int|null}>
     */
    public function defaultsTable(): array
    {
        return $this->companies->defaultsTable();
    }

    public function setDefaultCompany(int $countryId, ?int $companyId): void
    {
        $this->companies->setDefault($countryId, $companyId);
    }
}
