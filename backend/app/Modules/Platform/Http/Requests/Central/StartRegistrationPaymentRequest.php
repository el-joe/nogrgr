<?php

namespace App\Modules\Platform\Http\Requests\Central;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payment step (WEB-016c): the gateway; only gateways offered at checkout are accepted
 * (RegistrationPaymentService re-checks it is active with a driver).
 */
class StartRegistrationPaymentRequest extends FormRequest
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

    public function gateway(): PaymentGatewayCode
    {
        return PaymentGatewayCode::from((string) $this->validated('gateway'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['gateway' => __('platform::signup.payment.method')];
    }
}
