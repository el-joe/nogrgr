<?php

namespace App\Modules\Commerce\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Internal admin note on an order.
 */
class AdminNoteRequest extends FormRequest
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
        return ['note' => ['required', 'string', 'min:2', 'max:2000']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('commerce::orders_admin.notes.field')];
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
