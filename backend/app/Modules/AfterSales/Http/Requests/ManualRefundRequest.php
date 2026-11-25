<?php

namespace App\Modules\AfterSales\Http\Requests;

use App\Modules\AfterSales\DTOs\ManualRefundData;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Refund modal (ADM-021b / VEN-021b): USD amount (≤ refundable, checked by the service
 * with RefundCalculator), reason and optional restock.
 */
class ManualRefundRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'restock' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => __('after-sales::refunds.fields.amount'), 'reason' => __('after-sales::refunds.fields.reason')];
    }

    public function toData(): ManualRefundData
    {
        return new ManualRefundData(Money::of((string) $this->validated('amount')), trim((string) $this->validated('reason')), $this->boolean('restock'));
    }
}
