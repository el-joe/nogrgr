<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\DTOs\ConnectionTestResult;
use App\Modules\Integrations\DTOs\PaymentGatewayData;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use App\Modules\Integrations\Services\Testing\GatewayConnectionTester;
use App\Modules\Integrations\Support\SecretMask;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Central payment gateways (ADM-100a/b): catalogue rows, active switch, fees and the
 * two per-purpose configs. Changing credentials or mode clears the last test, so a
 * config is only "verified" for what was actually tested.
 */
class PaymentGatewayService extends BaseService
{
    public function __construct(
        private readonly PaymentGatewayRepositoryContract $gateways,
        private readonly GatewayConnectionTester $tester,
        private readonly Auditor $auditor,
        private readonly CurrencyRepositoryContract $currencies,
    ) {}

    /**
     * code => "SAR — Saudi Riyal" of the active currencies (settlement currencies select).
     *
     * @return array<string, string>
     */
    public function currencyOptions(): array
    {
        $options = [];

        foreach ($this->currencies->active() as $currency) {
            $options[$currency->code] = $currency->code.' — '.$currency->name;
        }

        return $options;
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function all(): Collection
    {
        $this->gateways->ensure(PaymentGatewayCode::cases());

        return $this->gateways->listed();
    }

    public function find(PaymentGatewayCode $code): PaymentGateway
    {
        $gateway = $this->gateways->findByCode($code);

        if ($gateway === null) {
            $this->gateways->ensure(PaymentGatewayCode::cases());
            $gateway = $this->gateways->findByCode($code);
        }

        return $gateway ?? throw new DomainException('Unknown gateway.');
    }

    public function config(PaymentGateway $gateway, GatewayPurpose $purpose): PaymentGatewayConfig
    {
        return $this->gateways->configFor($gateway, $purpose);
    }

    /**
     * Credential form state of a config: plain values, secrets masked only.
     *
     * @return array<string, array{value: string|null, masked: string|null, stored: bool}>
     */
    public function credentialForm(PaymentGateway $gateway, GatewayPurpose $purpose): array
    {
        $config = $this->config($gateway, $purpose);
        $form = [];

        foreach ($gateway->code->credentialFields() as $field) {
            $value = $config->credential($field->key);
            $form[$field->key] = $field->isSecret()
                ? ['value' => null, 'masked' => SecretMask::mask($value), 'stored' => $value !== null]
                : ['value' => $value, 'masked' => null, 'stored' => $value !== null];
        }

        return $form;
    }

    public function update(PaymentGateway $gateway, PaymentGatewayData $data): PaymentGateway
    {
        $this->transaction(function () use ($gateway, $data): void {
            $this->gateways->update($gateway, [
                'fee_percent_bps' => $data->feePercentBps,
                'fee_fixed_minor' => $data->feeFixedMinor,
            ]);

            foreach ($data->configs as $configData) {
                $config = $this->config($gateway, $configData->purpose);
                $credentials = array_filter([...($config->credentials ?? []), ...$configData->credentials], static fn (mixed $value): bool => filled($value));
                $changed = $credentials !== ($config->credentials ?? []) || $config->mode !== $configData->mode;

                $attributes = [
                    'is_enabled' => $configData->isEnabled,
                    'mode' => $configData->mode,
                    'credentials' => $credentials,
                    'currencies' => array_values(array_unique($configData->currencies)),
                ];

                if ($changed) {
                    $attributes += ['verified_at' => null, 'last_error' => null, 'last_tested_at' => null];
                }

                $this->gateways->saveConfig($config, $attributes);
            }
        }, $this->connection());

        $gateway->load('configs');
        $this->auditor->record('integrations.gateway_updated', $gateway, [
            'code' => $gateway->code->value,
            'fee_percent_bps' => $data->feePercentBps,
            'fee_fixed_minor' => $data->feeFixedMinor,
        ]);

        return $gateway;
    }

    public function setActive(PaymentGateway $gateway, bool $active): void
    {
        if ($active && ! $gateway->canBeActivated()) {
            throw new DomainException(__('integrations::admin.gateways.activate_needs_config', ['name' => $gateway->code->brand()]));
        }

        $this->gateways->update($gateway, ['is_active' => $active]);
        $this->auditor->record($active ? 'integrations.gateway_activated' : 'integrations.gateway_deactivated', $gateway, ['code' => $gateway->code->value]);
    }

    public function test(PaymentGateway $gateway, GatewayPurpose $purpose): ConnectionTestResult
    {
        $config = $this->config($gateway, $purpose);
        $result = $this->tester->test($gateway->code, $config->mode ?? GatewayMode::Test, $config->credentials ?? []);

        if ($config->exists) {
            $this->gateways->saveConfig($config, [
                'last_tested_at' => Carbon::now(),
                'verified_at' => $result->ok ? Carbon::now() : null,
                'last_error' => $result->ok ? null : mb_substr($result->error?->value.($result->detail !== null ? ': '.$result->detail : ''), 0, 500),
            ]);
        }

        $this->auditor->record('integrations.gateway_tested', $gateway, ['purpose' => $purpose->value, 'ok' => $result->ok, 'error' => $result->error?->value]);

        return $result;
    }

    private function connection(): ?string
    {
        return $this->gateways->query()->getModel()->getConnectionName();
    }
}
