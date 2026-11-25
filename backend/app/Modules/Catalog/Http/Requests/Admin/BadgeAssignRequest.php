<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assign products modal (ADM-017f): picked product ids and / or a whole category.
 */
class BadgeAssignRequest extends FormRequest
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
            'product_ids' => ['nullable', 'array', 'max:500', 'required_without:category_id'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
            'category_id' => ['nullable', 'integer', 'required_without:product_ids', Rule::exists(Category::class, 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_ids' => __('catalog::badges.fields.products'),
            'category_id' => __('catalog::badges.fields.category'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'product_ids' => array_map('intval', (array) ($this->validated('product_ids') ?? [])),
            'category_id' => $this->filled('category_id') ? (int) $this->validated('category_id') : null,
        ];
    }
}
