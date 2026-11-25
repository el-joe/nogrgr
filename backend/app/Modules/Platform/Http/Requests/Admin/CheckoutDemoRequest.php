<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\Support\CheckoutDemoSamples;
use App\Modules\Platform\Support\MotionDemoSamples;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout address-step gallery options:
 * ?family=ecommet|elora|souqify&dir=ltr|rtl&empty=1&shipping=ok|no_country|unavailable|not_sold&gateway=0|1.
 */
class CheckoutDemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'family' => ['nullable', Rule::in(MotionDemoSamples::FAMILIES)],
            'dir' => ['nullable', Rule::in(['ltr', 'rtl'])],
            'empty' => ['nullable', 'boolean'],
            'shipping' => ['nullable', Rule::in(CheckoutDemoSamples::SHIPPING_STATES)],
            'gateway' => ['nullable', 'boolean'],
        ];
    }

    public function family(): string
    {
        return (string) ($this->validated('family') ?? 'ecommet');
    }

    public function direction(): ?string
    {
        $direction = $this->validated('dir');

        return is_string($direction) ? $direction : null;
    }

    public function startsEmpty(): bool
    {
        return $this->boolean('empty');
    }

    public function shippingState(): string
    {
        return (string) ($this->validated('shipping') ?? 'ok');
    }

    public function gatewayAvailable(): bool
    {
        return $this->validated('gateway') === null || $this->boolean('gateway');
    }
}
