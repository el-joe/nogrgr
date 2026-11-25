<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use App\Modules\Catalog\DTOs\Requests\PaymentRequestData;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * New payment request (USD amount with up to 2 decimals, optional due date and note).
 */
class PaymentRequestStoreRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => (string) __('catalog::requests.payments.label'),
            'amount' => (string) __('catalog::requests.payments.amount'),
            'due_at' => (string) __('catalog::requests.payments.due_at'),
            'note' => (string) __('catalog::requests.payments.note'),
        ];
    }

    public function toData(): PaymentRequestData
    {
        $due = $this->validated('due_at');
        $note = $this->validated('note');

        return new PaymentRequestData(
            label: (string) $this->validated('label'),
            amountMinor: Money::of((string) $this->validated('amount'))->minor,
            dueAt: is_string($due) && $due !== '' ? Carbon::parse($due)->endOfDay() : null,
            note: is_string($note) && trim($note) !== '' ? trim($note) : null,
        );
    }
}
