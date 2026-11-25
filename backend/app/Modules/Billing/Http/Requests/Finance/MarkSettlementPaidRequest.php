<?php

namespace App\Modules\Billing\Http\Requests\Finance;

use App\Modules\Billing\DTOs\Finance\ManualSettlementData;
use App\Modules\Billing\Enums\SettlementMethod;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * ADM-050a mark settlement paid: amount (USD), method, reference (unless payout offset),
 * note, proof (PDF / image / ZIP ≤ 10 MB).
 */
class MarkSettlementPaidRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::enum(SettlementMethod::class)],
            'reference' => [Rule::requiredIf(fn (): bool => $this->input('method') !== SettlementMethod::PayoutOffset->value), 'nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,zip'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => (string) __('billing::finance.settle.amount'),
            'method' => (string) __('billing::finance.settle.method'),
            'reference' => (string) __('billing::finance.settle.reference'),
            'note' => (string) __('billing::finance.settle.note'),
            'attachment' => (string) __('billing::finance.settle.attachment'),
        ];
    }

    public function toData(): ManualSettlementData
    {
        $file = $this->file('attachment');

        return new ManualSettlementData(
            amount: Money::of((string) $this->validated('amount'))->minor,
            method: SettlementMethod::from((string) $this->validated('method')),
            reference: $this->filled('reference') ? trim((string) $this->validated('reference')) : null,
            note: $this->filled('note') ? trim((string) $this->validated('note')) : null,
            attachment: $file instanceof UploadedFile ? $file : null,
        );
    }
}
