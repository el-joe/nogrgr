<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\Support\StoreCategoryRef;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Category sort (VEN-017c): every store category ref ("central:5", "store:3") in order.
 */
class CategorySortRequest extends FormRequest
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
            'ids' => ['required', 'array', 'max:2000'],
            'ids.*' => ['string', 'distinct', 'regex:/^(central|store):[1-9]\d{0,18}$/'],
        ];
    }

    /**
     * @return list<StoreCategoryRef>
     */
    public function refs(): array
    {
        return array_values(array_filter(array_map(StoreCategoryRef::tryParse(...), (array) $this->validated('ids'))));
    }
}
