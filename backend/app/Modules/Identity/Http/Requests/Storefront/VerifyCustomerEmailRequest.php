<?php

namespace App\Modules\Identity\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 6-digit e-mail confirmation code (the page sends `code` or the six `digits[]` boxes).
 */
class VerifyCustomerEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $digits = $this->input('digits');

        if (! $this->filled('code') && is_array($digits)) {
            $this->merge(['code' => implode('', array_map(static fn ($digit): string => (string) $digit, $digits))]);
        }

        $this->merge(['code' => preg_replace('/\D+/', '', (string) $this->input('code'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
            'redirect' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
