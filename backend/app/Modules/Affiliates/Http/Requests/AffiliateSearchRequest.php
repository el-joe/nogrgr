<?php

namespace App\Modules\Affiliates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Select2 affiliate search (promo code form).
 */
class AffiliateSearchRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function term(): string
    {
        return trim((string) ($this->validated('q') ?? ''));
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
