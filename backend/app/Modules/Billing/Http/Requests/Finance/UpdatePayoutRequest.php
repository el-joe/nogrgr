<?php

namespace App\Modules\Billing\Http\Requests\Finance;

use App\Modules\Billing\DTOs\Finance\PayoutUpdateData;
use App\Modules\Billing\Enums\PayoutStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-052b edit payout: status and transaction reference (required once paid).
 */
class UpdatePayoutRequest extends FormRequest
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
            'status' => ['required', Rule::enum(PayoutStatus::class)],
            'transaction_reference' => [Rule::requiredIf(fn (): bool => $this->input('status') === PayoutStatus::Paid->value), 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => (string) __('billing::finance.payouts.fields.status'),
            'transaction_reference' => (string) __('billing::finance.payouts.fields.reference'),
        ];
    }

    public function toData(): PayoutUpdateData
    {
        return new PayoutUpdateData(
            status: PayoutStatus::from((string) $this->validated('status')),
            transactionReference: $this->filled('transaction_reference') ? trim((string) $this->validated('transaction_reference')) : null,
        );
    }
}
