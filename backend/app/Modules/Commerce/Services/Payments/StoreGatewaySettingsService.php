<?php

namespace App\Modules\Commerce\Services\Payments;

use App\Modules\Commerce\DTOs\Payments\StoreGatewayRow;
use App\Modules\Commerce\DTOs\Payments\StoreGatewaySettingsData;
use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Commerce\Repositories\Contracts\StorePaymentGatewayRepositoryContract;
use App\Modules\Integrations\DTOs\ConnectionTestResult;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use App\Modules\Integrations\Services\Testing\GatewayConnectionTester;
use App\Modules\Integrations\Support\SecretMask;
use App\Shared\Services\BaseService;

/**
 * Store payment gateway settings (VEN-078 / 078a) on the batch-2 store_payment_gateways
 * rows: the gateways the platform offers for storefront orders (central gateway active
 * with its vendor-payments config + a storefront driver), each with active / hidden
 * switches, "use my own merchant account" keys (encrypted cast; shown masked, a blank
 * secret keeps the stored one), sandbox / live, a connection check (own keys) and one
 * primary gateway. Whether checkout lists it is StorefrontGatewayService's rule.
 */
class StoreGatewaySettingsService extends BaseService
{
    public function __construct(
        private readonly StorePaymentGatewayRepositoryContract $rows,
        private readonly PaymentGatewayRepositoryContract $central,
        private readonly StorefrontGatewayService $storefront,
        private readonly GatewayConnectionTester $tester,
    ) {}

    /**
     * @return list<StoreGatewayRow>
     */
    public function list(): array
    {
        $list = [];

        foreach ($this->offeredCentral() as $gateway) {
            $row = $this->rows->firstOrNewByCode($gateway->code);
            $ready = $row->exists && $this->storefront->option($row, $gateway) !== null && $row->is_active && ! $row->is_hidden;

            $list[] = new StoreGatewayRow($gateway->code, $row, $ready, $this->state($row, $ready));
        }

        return $list;
    }

    public function find(PaymentGatewayCode $code): StoreGatewayRow
    {
        foreach ($this->list() as $item) {
            if ($item->code === $code) {
                return $item;
            }
        }

        throw CommerceException::rule('gateway_not_offered', 404);
    }

    /**
     * Credential form of a row: non-secret values in clear, secrets masked.
     *
     * @return array<string, array{label: string, secret: bool, required: bool, value: string|null, masked: string|null, stored: bool}>
     */
    public function credentialForm(StorePaymentGateway $row): array
    {
        $form = [];

        foreach ($row->code->credentialFields() as $field) {
            $value = $row->credentials[$field->key] ?? null;
            $form[$field->key] = [
                'label' => (string) __('integrations::admin.gateways.credentials.'.$field->key),
                'secret' => $field->isSecret(),
                'required' => $field->required,
                'value' => $field->isSecret() ? null : $value,
                'masked' => $field->isSecret() ? SecretMask::mask($value) : null,
                'stored' => $value !== null && $value !== '',
            ];
        }

        return $form;
    }

    public function update(PaymentGatewayCode $code, StoreGatewaySettingsData $data): StorePaymentGateway
    {
        $row = $this->find($code)->row;
        $credentials = $row->credentials ?? [];
        $changed = $row->mode !== $data->mode;

        foreach ($code->credentialFields() as $field) {
            $value = $data->credentials[$field->key] ?? null;
            $value = is_string($value) ? trim($value) : null;

            if ($field->isSecret() && ($value === null || $value === '')) {
                continue;
            }

            if (($credentials[$field->key] ?? null) !== ($value === '' ? null : $value)) {
                $changed = true;
            }

            if ($value === null || $value === '') {
                unset($credentials[$field->key]);
            } else {
                $credentials[$field->key] = $value;
            }
        }

        if ($data->useOwn && ! $this->hasRequiredKeys($code, $credentials)) {
            throw CommerceException::rule('gateway_keys_required');
        }

        $row->fill([
            'is_active' => $data->isActive,
            'is_hidden' => $data->isHidden,
            'use_own' => $data->useOwn,
            'mode' => $data->mode,
            'credentials' => $credentials === [] ? null : $credentials,
        ]);

        if ($changed) {
            $row->fill(['connection_status' => GatewayConnection::NotConnected, 'last_error' => null]);
        }

        $row->save();

        return $row;
    }

    public function setPrimary(PaymentGatewayCode $code): StorePaymentGateway
    {
        $item = $this->find($code);

        if (! $item->ready) {
            throw CommerceException::rule('gateway_not_ready');
        }

        $this->rows->markPrimary($item->row);

        return $item->row;
    }

    /**
     * "Check connection" of the store's own keys (central keys are the platform's job).
     */
    public function test(PaymentGatewayCode $code): ConnectionTestResult
    {
        $row = $this->find($code)->row;

        if (! $row->use_own) {
            throw CommerceException::rule('gateway_uses_platform');
        }

        $result = $this->tester->test($code, $row->mode, $row->credentials ?? []);
        $row->forceFill([
            'connection_status' => $result->ok ? GatewayConnection::Connected : GatewayConnection::NotConnected,
            'last_tested_at' => now(),
            'last_error' => $result->ok ? null : mb_substr($result->error?->value.($result->detail !== null ? ': '.$result->detail : ''), 0, 500),
        ])->save();

        return $result;
    }

    /**
     * Payment readiness checklist (VEN-078 side card).
     *
     * @return list<array{key: string, done: bool}>
     */
    public function readiness(): array
    {
        $list = $this->list();
        $own = array_filter($list, static fn (StoreGatewayRow $item): bool => $item->row->exists && $item->row->use_own && $item->row->is_active);

        return [
            ['key' => 'gateway_ready', 'done' => array_filter($list, static fn (StoreGatewayRow $item): bool => $item->ready) !== []],
            ['key' => 'own_keys_valid', 'done' => array_filter($own, static fn (StoreGatewayRow $item): bool => $item->row->connection_status !== GatewayConnection::Connected) === []],
            ['key' => 'primary_set', 'done' => array_filter($list, static fn (StoreGatewayRow $item): bool => $item->row->is_primary && $item->ready) !== []],
        ];
    }

    /**
     * @return list<PaymentGateway>
     */
    private function offeredCentral(): array
    {
        return $this->central->usableFor(StorefrontGatewayService::PURPOSE)
            ->filter(fn (PaymentGateway $gateway): bool => $this->storefront->supports($gateway->code))
            ->sortBy(static fn (PaymentGateway $gateway): string => $gateway->code->brand())
            ->values()
            ->all();
    }

    private function state(StorePaymentGateway $row, bool $ready): string
    {
        return match (true) {
            ! $row->exists || ! $row->is_active => 'off',
            $row->is_hidden => 'hidden',
            $ready => $row->mode === GatewayMode::Live ? 'ready' : 'testing',
            $row->use_own && $row->last_error !== null => 'key_rejected',
            default => 'needs_attention',
        };
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function hasRequiredKeys(PaymentGatewayCode $code, array $credentials): bool
    {
        foreach ($code->credentialFields() as $field) {
            if ($field->required && blank($credentials[$field->key] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
