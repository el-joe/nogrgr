<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Enums\LegacyWriteResult;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Old central `payment_gateways` (one row per code × type) → `payment_gateways` (fees,
 * active) + `payment_gateway_configs` (per purpose: enabled, mode, credentials).
 * Old type `vendor_payments` → purpose vendor_payments; `orders` (platform checkout) →
 * tenant_registration (the platform's own checkout config). Credentials are decrypted
 * with the old APP_KEY, filtered to the new gateway's credential fields and encrypted
 * again with ours (same format as the `encrypted:array` cast). Gateways the new system
 * does not support are reported and skipped.
 */
class GatewaysStep implements LegacyStepHandler
{
    /** Old credential key => new key, per gateway (old field names that changed). */
    private const ALIASES = [
        'stripe' => ['key' => 'publishable_key', 'secret' => 'secret_key'],
        'myfatoorah' => ['token' => 'api_key'],
    ];

    public function __construct(private readonly Encrypter $encrypter) {}

    public function step(): LegacyStep
    {
        return LegacyStep::Gateways;
    }

    public function run(LegacyContext $context): void
    {
        $writer = $context->central;

        foreach ($context->source->central()->table('payment_gateways')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'payment_gateways');
            $code = PaymentGatewayCode::tryFrom((string) $row->code);

            if ($code === null) {
                $context->report->issue(LegacyIssueLevel::Info, 'central', 'payment_gateways', (string) $row->code, 'Gateway not supported by the new system (skipped)');
                $context->report->skipped('central', 'payment_gateways');

                continue;
            }

            $gatewayId = $writer->connection()->table('payment_gateways')->where('code', $code->value)->value('id');
            $active = $row->status === 'active';
            $gatewayAttributes = [
                'fee_percent_bps' => LegacyValues::bps($row->transaction_fee_percentage),
                'fee_fixed_minor' => LegacyValues::minor($row->transaction_fee_fixed),
            ];

            if ($active) {
                $gatewayAttributes['is_active'] = true;
            }

            $gatewayId = $writer->upsert('payment_gateway', $code->value, 'payment_gateways', $gatewayAttributes + ($gatewayId === null ? ['code' => $code->value, 'is_active' => $active, 'sort_order' => 0] : []), ['code' => $code->value]);
            $purpose = $row->type === 'vendor_payments' ? GatewayPurpose::VendorPayments : GatewayPurpose::TenantRegistration;
            $credentials = $this->credentials($context, $row, $code);

            $attributes = [
                'payment_gateway_id' => $gatewayId,
                'purpose' => $purpose->value,
                'is_enabled' => $active,
                'mode' => $row->mode === 'live' ? 'live' : 'test',
                'verified_at' => null,
                'last_error' => null,
            ];

            if ($credentials !== []) {
                $attributes['credentials'] = $this->encrypter->encrypt(json_encode($credentials), false);
            }

            $writer->upsert('payment_gateway_config', (int) $row->id, 'payment_gateway_configs', $attributes, ['payment_gateway_id' => $gatewayId, 'purpose' => $purpose->value]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function credentials(LegacyContext $context, object $row, PaymentGatewayCode $code): array
    {
        if ($row->credentials === null || $row->credentials === '') {
            return [];
        }

        $plain = $context->source->decrypt((string) $row->credentials) ?? (string) $row->credentials;
        $decoded = LegacyValues::json($plain);

        if ($decoded === null) {
            $context->report->issue(LegacyIssueLevel::Error, 'central', 'payment_gateways', (int) $row->id, 'Gateway credentials do not decrypt with the old APP_KEY (re-enter them in Admin > Payment gateways)');

            return [];
        }

        $allowed = array_map(static fn ($field): string => $field->key, $code->credentialFields());
        $kept = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! is_scalar($value) || (string) $value === '' || $key === 'sandbox') {
                continue; // `sandbox` is the old mode flag; the mode column carries it
            }

            $key = self::ALIASES[$code->value][$key] ?? $key;

            if (in_array($key, $allowed, true)) {
                $kept[$key] = (string) $value;
            } else {
                $context->report->issue(LegacyIssueLevel::Info, 'central', 'payment_gateways', $code->value.'.'.$key, 'Old credential field has no new equivalent (dropped)');
            }
        }

        if ($kept !== []) {
            $context->report->wrote('central', 'gateway_credentials', LegacyWriteResult::Updated);
        }

        return $kept;
    }
}
