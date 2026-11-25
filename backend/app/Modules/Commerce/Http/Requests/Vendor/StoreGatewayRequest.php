<?php

namespace App\Modules\Commerce\Http\Requests\Vendor;

use App\Modules\Commerce\DTOs\Payments\StoreGatewaySettingsData;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-078a: switches, mode and the gateway's own credential fields (blank secret = keep).
 */
class StoreGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'is_active' => ['required', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'use_own' => ['required', 'boolean'],
            'mode' => ['required', Rule::enum(GatewayMode::class)],
            'credentials' => ['nullable', 'array'],
        ];

        foreach ($this->code()->credentialFields() as $field) {
            $rules['credentials.'.$field->key] = ['nullable', 'string', 'max:'.$field->maxLength];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['mode' => __('commerce::gateways.fields.mode'), 'use_own' => __('commerce::gateways.fields.use_own')];

        foreach ($this->code()->credentialFields() as $field) {
            $attributes['credentials.'.$field->key] = (string) __('integrations::admin.gateways.credentials.'.$field->key);
        }

        return $attributes;
    }

    public function code(): PaymentGatewayCode
    {
        return PaymentGatewayCode::tryFrom((string) $this->route('code')) ?? abort(404);
    }

    public function toData(): StoreGatewaySettingsData
    {
        /** @var array<string, string|null> $credentials */
        $credentials = (array) ($this->validated('credentials') ?? []);

        return new StoreGatewaySettingsData(
            isActive: $this->boolean('is_active'),
            isHidden: $this->boolean('is_hidden'),
            useOwn: $this->boolean('use_own'),
            mode: GatewayMode::from((string) $this->validated('mode')),
            credentials: $credentials,
        );
    }
}
