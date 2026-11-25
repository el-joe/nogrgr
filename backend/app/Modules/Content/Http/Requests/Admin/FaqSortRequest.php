<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FAQ drag-sort (ADM-083): the full id list in the new order.
 */
class FaqSortRequest extends FormRequest
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
            'ids.*' => ['integer', 'distinct', Rule::exists(Faq::class, 'id')],
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
