<?php

namespace App\Modules\Catalog\Http\Requests\Vendor\Requests;

use App\Modules\Catalog\Services\Requests\RequestCheckoutService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pay a payment request: one of the gateways offered for vendor payments.
 */
class RequestPayRequest extends FormRequest
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
        $codes = app(RequestCheckoutService::class)->gateways()->map(static fn (PaymentGateway $gateway): string => $gateway->code->value)->all();

        return [
            'gateway' => ['required', 'string', Rule::in($codes)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['gateway' => (string) __('catalog::vendor_requests.payment.gateway')];
    }

    public function gateway(): PaymentGatewayCode
    {
        return PaymentGatewayCode::from((string) $this->validated('gateway'));
    }
}
