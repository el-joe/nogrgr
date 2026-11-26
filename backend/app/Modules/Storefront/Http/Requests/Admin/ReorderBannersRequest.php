<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Drag to sort (ADM-085): the banner ids of the visible list in their new order.
 */
class ReorderBannersRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
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
