<?php

namespace App\Modules\Affiliates\Http\Requests;

use App\Modules\Affiliates\DTOs\PayoutData;
use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * ADM-060b / 062 "Pay out": amount (USD), method, reference, notes, proof file.
 */
class PayoutFormRequest extends FormRequest
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
            'method' => ['required', Rule::enum(PayoutMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,zip', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => (string) __('affiliates::admin.payout.amount'),
            'method' => (string) __('affiliates::admin.payout.method'),
            'reference' => (string) __('affiliates::admin.payout.reference'),
            'notes' => (string) __('affiliates::admin.payout.notes'),
            'attachment' => (string) __('affiliates::admin.payout.attachment'),
        ];
    }

    public function toData(): PayoutData
    {
        $file = $this->file('attachment');

        return new PayoutData(
            amount: Money::of((string) $this->validated('amount'))->minor,
            method: PayoutMethod::from((string) $this->validated('method')),
            reference: $this->filled('reference') ? trim((string) $this->validated('reference')) : null,
            notes: $this->filled('notes') ? trim((string) $this->validated('notes')) : null,
            attachment: $file instanceof UploadedFile ? $file : null,
        );
    }
}
