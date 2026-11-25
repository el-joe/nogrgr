<?php

namespace App\Modules\Integrations\Http\Requests\Admin;

use App\Modules\Integrations\DTOs\GatewayConfigData;
use App\Modules\Integrations\DTOs\PaymentGatewayData;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Services\PaymentGatewayService;
use App\Modules\Localization\Models\Currency;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Gateway edit (ADM-100b): fee % (stored as basis points) + fixed USD fee (minor
 * units), and per purpose: enabled, mode, credentials (blank secret = keep) and
 * settlement currencies (active currencies only).
 */
class UpdatePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function code(): PaymentGatewayCode
    {
        /** @var PaymentGatewayCode $code */
        $code = $this->route('gateway');

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'fee_fixed' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'configs' => ['required', 'array'],
        ];

        foreach (GatewayPurpose::cases() as $purpose) {
            $prefix = 'configs.'.$purpose->value;
            $enabled = $this->boolean($prefix.'.enabled');

            $rules[$prefix.'.enabled'] = ['required', 'boolean'];
            $rules[$prefix.'.mode'] = ['required', Rule::enum(GatewayMode::class)];
            $rules[$prefix.'.currencies'] = [$enabled ? 'required' : 'nullable', 'array', 'max:20'];
            $rules[$prefix.'.currencies.*'] = ['string', 'size:3', Rule::exists(Currency::class, 'code')->where('is_active', true)];

            foreach ($this->code()->credentialFields() as $field) {
                $presence = $field->required && $enabled && ! $field->isSecret() ? 'required' : 'nullable';
                $rules[$prefix.'.credentials.'.$field->key] = [$presence, ...$field->rules()];
            }
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $service = app(PaymentGatewayService::class);
            $gateway = $service->find($this->code());

            foreach (GatewayPurpose::cases() as $purpose) {
                if (! $this->boolean('configs.'.$purpose->value.'.enabled')) {
                    continue;
                }

                $stored = $service->credentialForm($gateway, $purpose);

                foreach ($this->code()->credentialFields() as $field) {
                    $key = 'configs.'.$purpose->value.'.credentials.'.$field->key;

                    if ($field->isSecret() && $field->required && blank($this->input($key)) && ! ($stored[$field->key]['stored'] ?? false)) {
                        $validator->errors()->add($key, __('validation.required', ['attribute' => $this->attributes()[$key] ?? $field->key]));
                    }
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'fee_percent' => __('integrations::admin.gateways.fields.fee_percent'),
            'fee_fixed' => __('integrations::admin.gateways.fields.fee_fixed'),
        ];

        foreach (GatewayPurpose::cases() as $purpose) {
            $prefix = 'configs.'.$purpose->value;
            $attributes[$prefix.'.currencies'] = __('integrations::admin.gateways.fields.currencies');
            $attributes[$prefix.'.currencies.*'] = __('integrations::admin.gateways.fields.currencies');
            $attributes[$prefix.'.mode'] = __('integrations::admin.gateways.fields.mode');

            foreach ($this->code()->credentialFields() as $field) {
                $attributes[$prefix.'.credentials.'.$field->key] = __('integrations::admin.gateways.credentials.'.$field->key);
            }
        }

        return $attributes;
    }

    public function payload(): PaymentGatewayData
    {
        $configs = [];

        foreach (GatewayPurpose::cases() as $purpose) {
            $prefix = 'configs.'.$purpose->value;
            $credentials = [];

            foreach ($this->code()->credentialFields() as $field) {
                $value = $this->input($prefix.'.credentials.'.$field->key);

                if (is_string($value) && trim($value) !== '') {
                    $credentials[$field->key] = trim($value);
                }
            }

            $configs[] = new GatewayConfigData(
                purpose: $purpose,
                isEnabled: $this->boolean($prefix.'.enabled'),
                mode: GatewayMode::from((string) $this->input($prefix.'.mode')),
                credentials: $credentials,
                currencies: array_values(array_map(static fn (mixed $code): string => strtoupper((string) $code), (array) $this->input($prefix.'.currencies', []))),
            );
        }

        return new PaymentGatewayData(
            feePercentBps: (int) round(((float) $this->input('fee_percent')) * 100),
            feeFixedMinor: Money::of((string) $this->input('fee_fixed'))->minor,
            configs: $configs,
        );
    }
}
