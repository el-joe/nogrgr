<?php

namespace App\Modules\Commerce\Http\Requests\StoreApi;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutOptionsRequest extends FormRequest
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
        return [
            'shipping_address_id' => ['nullable', 'integer', 'min:1'],
            'billing_address_id' => ['nullable', 'integer', 'min:1'],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
        ];
    }
}
