<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\DTOs\Vendor\StoreProductUpdateData;
use App\Modules\Commerce\Enums\ProfitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Product edit (VEN-012): pricing rows, store category / badge placement (sent with
 * placement=1) and flags. Central fields (content, images, sku, prices, stock, weight,
 * variants…) are protected: sending any of them is a 422 that points the vendor to an
 * edit request.
 */
class StoreProductUpdateRequest extends FormRequest
{
    /**
     * Central product fields a vendor can only change through an edit request.
     *
     * @var list<string>
     */
    public const PROTECTED_FIELDS = [
        'name', 'label', 'summary', 'description', 'meta_title', 'meta_keywords', 'meta_description',
        'sku', 'slug', 'base_price', 'sale_price', 'cost_price', 'price', 'images', 'status',
        'allowed_country_ids', 'central_product_id', 'source', 'stock', 'weight_grams', 'variants',
        'attributes', 'labels',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'pricing' => ['nullable', 'array'],
            'pricing.*' => ['array'],
            'pricing.*.*' => ['array'],
            'pricing.*.*.profit_type' => ['required', Rule::enum(ProfitType::class)],
            'pricing.*.*.profit_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'placement' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array', 'max:50'],
            'category_ids.*' => ['integer', 'min:1'],
            'badge_ids' => ['nullable', 'array', 'max:50'],
            'badge_ids.*' => ['integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ];

        foreach (self::PROTECTED_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (self::PROTECTED_FIELDS as $field) {
            $messages[$field.'.prohibited'] = (string) __('catalog::vendor.errors.protected_field');
        }

        return $messages;
    }

    public function toData(): StoreProductUpdateData
    {
        /** @var array<string, array<string, array{profit_type: string, profit_value: float|int|string}>> $pricing */
        $pricing = (array) $this->validated('pricing', []);
        $placement = $this->boolean('placement');

        return new StoreProductUpdateData(
            pricing: $pricing,
            categoryIds: $placement ? array_values(array_map('intval', (array) $this->validated('category_ids', []))) : null,
            badgeIds: $placement ? array_values(array_map('intval', (array) $this->validated('badge_ids', []))) : null,
            isActive: $this->boolean('is_active'),
            isFeatured: $this->boolean('is_featured'),
        );
    }
}
