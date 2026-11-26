<?php

namespace App\Modules\Commerce\Http\Requests\StoreApi;

use App\Modules\Commerce\Enums\CancellationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Customer cancellation: optional reason code of the customer list
 * (`orders/cancellation-reasons`) and a free note (`reason`, required for "other").
 */
class CancelOrderRequest extends FormRequest
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
            'reason_code' => ['nullable', 'string', Rule::in(CancellationReason::customerValues())],
            'reason' => ['nullable', 'string', 'max:500', Rule::requiredIf(fn (): bool => $this->input('reason_code') === CancellationReason::Other->value)],
        ];
    }

    public function reasonCode(): ?CancellationReason
    {
        $code = $this->validated('reason_code');

        return is_string($code) ? CancellationReason::from($code) : null;
    }

    public function note(): ?string
    {
        $note = $this->validated('reason');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
