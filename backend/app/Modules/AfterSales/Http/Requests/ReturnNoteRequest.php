<?php

namespace App\Modules\AfterSales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Return note: internal, or a message to the customer.
 */
class ReturnNoteRequest extends FormRequest
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
        return ['note' => ['required', 'string', 'min:2', 'max:2000'], 'customer_visible' => ['nullable', 'boolean']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('after-sales::returns.fields.note')];
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }

    public function customerVisible(): bool
    {
        return $this->boolean('customer_visible');
    }
}
