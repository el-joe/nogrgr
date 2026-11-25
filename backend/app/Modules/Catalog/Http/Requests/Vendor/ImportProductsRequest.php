<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * New central products (VEN-011): import the selected ids, or every new product (all=1).
 */
class ImportProductsRequest extends FormRequest
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
            'all' => ['sometimes', 'boolean'],
            'ids' => ['required_unless:all,1,true', 'array', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return list<int>|null null = import everything new
     */
    public function ids(): ?array
    {
        return $this->boolean('all') ? null : array_values(array_map('intval', (array) $this->validated('ids', [])));
    }
}
