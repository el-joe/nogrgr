<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sort products page data (ADM-012): ?category_id=&search=&page= (every product sells in every country, #115)
 */
class ProductSortRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array{category_id: int|null, search: string|null}
     */
    public function filters(): array
    {
        return [
            'category_id' => $this->filled('category_id') ? (int) $this->validated('category_id') : null,
            'search' => $this->filled('search') ? (string) $this->validated('search') : null,
        ];
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
