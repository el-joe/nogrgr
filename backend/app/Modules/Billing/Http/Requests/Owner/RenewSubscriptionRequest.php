<?php

namespace App\Modules\Billing\Http\Requests\Owner;

use App\Modules\Billing\Services\SubscriptionRenewalService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renew a subscription (OWN-003): one of the gateways offered for renewals.
 * Ownership is checked by the controller (gate owner.subscriptions.manage).
 */
class RenewSubscriptionRequest extends FormRequest
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
        $offered = app(SubscriptionRenewalService::class)->gateways()
            ->map(static fn (PaymentGateway $gateway): string => $gateway->code->value)
            ->all();

        return [
            'gateway' => ['required', 'string', Rule::in($offered)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['gateway' => __('billing::owner.subscriptions.gateway')];
    }

    public function gateway(): PaymentGatewayCode
    {
        return PaymentGatewayCode::from((string) $this->validated('gateway'));
    }
}
