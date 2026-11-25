<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sort products page data (ADM-012): ?category_id=&country_id=&search=&page=
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
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array{category_id: int|null, country_id: int|null, search: string|null}
     */
    public function filters(): array
    {
        return [
            'category_id' => $this->filled('category_id') ? (int) $this->validated('category_id') : null,
            'country_id' => $this->filled('country_id') ? (int) $this->validated('country_id') : null,
            'search' => $this->filled('search') ? (string) $this->validated('search') : null,
        ];
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
