<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\ProgramSettingsData;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Integrations\DTOs\IntegrationSettingsData;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Services\IntegrationSettingsService;
use App\Shared\Money\Money;

/**
 * Affiliate program settings (ADM-060c) — the `affiliates` business section of
 * `integration_settings` (decision #69: DB, never .env): referral cookie lifetime,
 * default commission (percentage of the first paid subscription payment) and minimum
 * payout.
 */
class ProgramSettingsService
{
    public function __construct(private readonly IntegrationSettingsService $settings) {}

    public function cookieDays(): int
    {
        return max(1, (int) $this->values()['cookie_days']);
    }

    public function defaultCommissionBps(): int
    {
        return max(0, min(10000, (int) $this->values()['default_commission_bps']));
    }

    /**
     * @return array{0: RateType, 1: int}
     */
    public function defaultCommission(): array
    {
        return [RateType::Percentage, $this->defaultCommissionBps()];
    }

    public function minPayout(): Money
    {
        return Money::ofMinor(max(0, (int) $this->values()['min_payout_cents']));
    }

    public function save(ProgramSettingsData $data): void
    {
        $this->settings->save(new IntegrationSettingsData(IntegrationSection::Affiliates, [
            'cookie_days' => $data->cookieDays,
            'default_commission_bps' => $data->defaultCommissionBps,
            'min_payout_cents' => $data->minPayoutCents,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        return $this->settings->values(IntegrationSection::Affiliates);
    }
}
