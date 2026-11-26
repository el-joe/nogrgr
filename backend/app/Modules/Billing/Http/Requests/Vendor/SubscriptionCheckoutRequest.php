<?php

namespace App\Modules\Billing\Http\Requests\Vendor;

use App\Modules\Billing\DTOs\VendorSubscriptionCheckoutData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Enums\VendorSubscriptionAction;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\InvoiceCheckoutService;
use App\Modules\Billing\Services\SubscriptionRenewalService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-060a checkout: renew / change plan use the subscription gateways (tenant
 * registration config), paying an invoice uses the vendor-payments gateways. The
 * invoice's store is checked by the service (permission middleware guards the route).
 */
class SubscriptionCheckoutRequest extends FormRequest
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
        $action = VendorSubscriptionAction::tryFrom((string) $this->input('action'));
        $gateways = $action === VendorSubscriptionAction::Invoice
            ? app(InvoiceCheckoutService::class)->gateways()
            : app(SubscriptionRenewalService::class)->gateways();

        return [
            'action' => ['required', 'string', Rule::in(VendorSubscriptionAction::values())],
            'gateway' => ['required', 'string', Rule::in($gateways->map(static fn (PaymentGateway $gateway): string => $gateway->code->value)->all())],
            'plan_id' => ['nullable', 'required_if:action,'.VendorSubscriptionAction::Change->value, 'integer', Rule::exists(Plan::class, 'id')->where('status', PlanStatus::Active->value)],
            'term' => ['nullable', 'required_if:action,'.VendorSubscriptionAction::Change->value, Rule::enum(BillingTerm::class)],
            'invoice_id' => ['nullable', 'required_if:action,'.VendorSubscriptionAction::Invoice->value, 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => __('billing::vendor_subscription.fields.action'),
            'gateway' => __('billing::vendor_subscription.fields.gateway'),
            'plan_id' => __('billing::vendor_subscription.fields.plan'),
            'term' => __('billing::vendor_subscription.fields.term'),
            'invoice_id' => __('billing::vendor_subscription.fields.invoice'),
        ];
    }

    public function toData(): VendorSubscriptionCheckoutData
    {
        $action = VendorSubscriptionAction::from((string) $this->validated('action'));
        $change = $action === VendorSubscriptionAction::Change;

        return new VendorSubscriptionCheckoutData(
            action: $action,
            gateway: PaymentGatewayCode::from((string) $this->validated('gateway')),
            planId: $change ? (int) $this->validated('plan_id') : null,
            term: $change ? BillingTerm::from((string) $this->validated('term')) : null,
            invoiceId: $action === VendorSubscriptionAction::Invoice ? (int) $this->validated('invoice_id') : null,
        );
    }
}
