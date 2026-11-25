<?php

namespace App\Modules\Commerce\Http\Requests\StoreApi;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPaymentRequest extends FormRequest
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
            'gateway' => ['required', 'string', Rule::enum(PaymentGatewayCode::class)],
        ];
    }
}
