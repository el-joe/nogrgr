<?php

namespace App\Modules\Localization\Http\Requests\Vendor;

use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Buy a language (VEN-073a) with one of the vendor-payment gateways (USD).
 */
class StoreLanguagePurchaseRequest extends FormRequest
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
        $codes = app(InvoiceCheckoutService::class)->gateways()->map(static fn (PaymentGateway $gateway): string => $gateway->code->value)->all();

        return ['gateway' => ['required', 'string', Rule::in($codes)]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['gateway' => (string) __('localization::vendor_languages.fields.gateway')];
    }

    public function gateway(): PaymentGatewayCode
    {
        return PaymentGatewayCode::from((string) $this->validated('gateway'));
    }
}
