<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Product sort (VEN-014): every store product id in the new display order.
 */
class SortRequest extends FormRequest
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
            'ids' => ['required', 'array', 'max:5000'],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->validated('ids')));
    }
}
