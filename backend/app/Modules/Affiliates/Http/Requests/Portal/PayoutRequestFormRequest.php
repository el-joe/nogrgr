<?php

namespace App\Modules\Affiliates\Http\Requests\Portal;

use App\Modules\Affiliates\DTOs\PayoutRequestData;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AFF-012a / VEN-055a payout request: amount in USD (two decimals) and an optional note.
 * Minimum / requestable limits are checked by PayoutRequestService.
 */
class PayoutRequestFormRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => (string) __('affiliates::panel.payouts.amount'),
            'note' => (string) __('affiliates::panel.payouts.note'),
        ];
    }

    public function toData(): PayoutRequestData
    {
        return new PayoutRequestData(
            amount: Money::of((string) $this->validated('amount'))->minor,
            note: $this->filled('note') ? trim((string) $this->validated('note')) : null,
        );
    }
}
