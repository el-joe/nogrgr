<?php

namespace App\Modules\Reporting\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Select2 store picker of the report and shopper filters (?q=&page=).
 */
class StoreSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function term(): ?string
    {
        $term = $this->validated('q');

        return is_string($term) && trim($term) !== '' ? trim($term) : null;
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
