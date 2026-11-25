<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A flat list in its new order: ids = [3, 1, 2] (labels, badge products, category products).
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
            'ids' => ['required', 'array', 'min:1', 'max:5000'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map('intval', array_values((array) $this->validated('ids')));
    }
}
