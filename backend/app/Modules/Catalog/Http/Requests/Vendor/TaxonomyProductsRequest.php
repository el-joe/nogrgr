<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\Support\StoreCategoryRef;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add products to a category / badge (VEN-017a, VEN-018a): store product ids, or for a
 * badge a whole category ("Add category").
 */
class TaxonomyProductsRequest extends FormRequest
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
        if ($this->routeIs('*.add-category')) {
            return ['category' => ['required', 'string', 'regex:/^(central|store):[1-9]\d{0,18}$/']];
        }

        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_ids' => (string) __('catalog::taxonomy.fields.products'),
            'category' => (string) __('catalog::taxonomy.fields.category'),
        ];
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('product_ids')));
    }

    public function categoryRef(): StoreCategoryRef
    {
        return StoreCategoryRef::tryParse((string) $this->validated('category')) ?? abort(422);
    }
}
