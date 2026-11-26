<?php

namespace App\Modules\Affiliates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ADM-061 "Reverse conversion": the reason is required.
 */
class ReverseConversionRequest extends FormRequest
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
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => (string) __('affiliates::admin.conversions.reason')];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
