<?php

namespace App\Modules\Billing\Http\Requests\Finance;

use App\Modules\Billing\DTOs\Finance\PayoutData;
use App\Modules\Billing\Enums\PayoutMethod;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-052a create payout: store, amount (USD), method, scheduled date, reference, note,
 * e-mail the vendor.
 */
class StorePayoutRequest extends FormRequest
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
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::enum(PayoutMethod::class)],
            'scheduled_for' => ['required', 'date', 'after_or_equal:today'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
            'notify_vendor' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => (string) __('billing::finance.payouts.fields.store'),
            'amount' => (string) __('billing::finance.payouts.fields.amount'),
            'method' => (string) __('billing::finance.payouts.fields.method'),
            'scheduled_for' => (string) __('billing::finance.payouts.fields.scheduled_for'),
            'transaction_reference' => (string) __('billing::finance.payouts.fields.reference'),
            'note' => (string) __('billing::finance.payouts.fields.note'),
        ];
    }

    public function toData(): PayoutData
    {
        return new PayoutData(
            tenantId: (int) $this->validated('tenant_id'),
            amount: Money::of((string) $this->validated('amount'))->minor,
            method: PayoutMethod::from((string) $this->validated('method')),
            scheduledFor: CarbonImmutable::parse((string) $this->validated('scheduled_for'))->startOfDay(),
            transactionReference: $this->filled('transaction_reference') ? trim((string) $this->validated('transaction_reference')) : null,
            note: $this->filled('note') ? trim((string) $this->validated('note')) : null,
            notifyVendor: $this->boolean('notify_vendor'),
        );
    }
}
