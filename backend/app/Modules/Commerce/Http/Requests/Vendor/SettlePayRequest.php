<?php

namespace App\Modules\Commerce\Http\Requests\Vendor;

use App\Modules\Commerce\Services\Settlement\SettlementService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pay platform (VEN-021d): one of the vendor-payments gateways.
 */
class SettlePayRequest extends FormRequest
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
        $codes = app(SettlementService::class)->gateways()->map(static fn (PaymentGateway $gateway): string => $gateway->code->value)->all();

        return ['gateway' => ['required', 'string', Rule::in($codes)]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['gateway' => __('commerce::settlement.gateway')];
    }

    public function gateway(): PaymentGatewayCode
    {
        return PaymentGatewayCode::from((string) $this->validated('gateway'));
    }
}
