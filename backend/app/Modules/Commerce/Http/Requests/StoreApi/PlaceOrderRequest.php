<?php

namespace App\Modules\Commerce\Http\Requests\StoreApi;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Place order. The idempotency key comes from the `Idempotency-Key` header (or the
 * `idempotency_key` field): the same key returns the first result instead of a second order.
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $header = $this->header('Idempotency-Key');

        if (is_string($header) && $header !== '' && ! $this->filled('idempotency_key')) {
            $this->merge(['idempotency_key' => $header]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
            'gateway' => ['required', 'string', Rule::enum(PaymentGatewayCode::class)],
            'shipping_address_id' => ['nullable', 'integer', 'min:1'],
            'billing_address_id' => ['nullable', 'integer', 'min:1'],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
        ];
    }
}
