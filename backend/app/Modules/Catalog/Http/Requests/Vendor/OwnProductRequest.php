<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\DTOs\Vendor\OwnProductData;
use App\Modules\Catalog\DTOs\Vendor\OwnVariantData;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Support\GalleryMedia;
use App\Shared\Money\Money;
use App\Shared\Support\Locale;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Own product create / edit (VEN-016c, Figma 12:17411): basics, countries, localized
 * content, flags, pricing (USD), variants and gallery.
 */
class OwnProductRequest extends FormRequest
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
        $fallback = (string) config('app.fallback_locale', 'en');
        $locales = array_keys(Locale::enabled());
        $productId = $this->route('product');
        $rules = [
            'name' => ['required', 'array'],
            "name.{$fallback}" => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'array'],
            'summary' => ['required', 'array'],
            "summary.{$fallback}" => ['required', 'string', 'max:500'],
            'description' => ['required', 'array'],
            "description.{$fallback}" => ['required', 'string', 'max:20000'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique(StoreProduct::class, 'sku')->ignore(is_numeric($productId) ? (int) $productId : null)],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii'],
            'weight_grams' => ['required', 'integer', 'min:0', 'max:1000000'],
            'all_countries' => ['sometimes', 'boolean'],
            'country_ids' => ['required_unless:all_countries,1,true', 'array', 'max:250'],
            'country_ids.*' => ['integer', 'min:1', Rule::in($this->storeCountryIds())],
            'category_ids' => ['required', 'array', 'min:1', 'max:20'],
            'category_ids.*' => ['integer', 'min:1'],
            'store_category_ids' => ['nullable', 'array', 'max:50'],
            'store_category_ids.*' => ['integer', 'min:1', Rule::exists(StoreCategory::class, 'id')],
            'price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'manage_stock' => ['sometimes', 'boolean'],
            'is_taxable' => ['sometimes', 'boolean'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', 'min:1'],
            'variants.*.title' => ['required', 'array'],
            "variants.*.title.{$fallback}" => ['required', 'string', 'max:120'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:10000000'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => GalleryMedia::rules(['file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240']),
            'images_keep' => ['nullable', 'array'],
            'images_keep.*' => ['integer', 'min:1'],
        ];

        foreach (['name', 'label', 'summary', 'description'] as $field) {
            $rules[$field.'.*'] = ['nullable', 'string', 'max:20000'];
        }

        $rules['name'][] = static function (string $attribute, mixed $value, Closure $fail) use ($locales): void {
            if (is_array($value) && array_diff(array_keys($value), $locales) !== []) {
                $fail((string) __('validation.in', ['attribute' => $attribute]));
            }
        };

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fallback = (string) config('app.fallback_locale', 'en');

        return [
            "name.{$fallback}" => (string) __('catalog::vendor.own.fields.name'),
            "summary.{$fallback}" => (string) __('catalog::vendor.own.fields.summary'),
            "description.{$fallback}" => (string) __('catalog::vendor.own.fields.description'),
            'category_ids' => (string) __('catalog::vendor.own.fields.category'),
            'country_ids' => (string) __('catalog::vendor.own.fields.countries'),
            'weight_grams' => (string) __('catalog::vendor.own.fields.weight'),
            'price' => (string) __('catalog::vendor.own.fields.base_price'),
            'sale_price' => (string) __('catalog::vendor.own.fields.sale_price'),
        ];
    }

    public function toData(): OwnProductData
    {
        $variants = [];

        foreach ((array) $this->validated('variants', []) as $row) {
            $variants[] = new OwnVariantData(
                id: isset($row['id']) ? (int) $row['id'] : null,
                title: (array) $row['title'],
                sku: isset($row['sku']) && $row['sku'] !== '' ? (string) $row['sku'] : null,
                price: Money::of((string) $row['price']),
                salePrice: isset($row['sale_price']) && $row['sale_price'] !== '' ? Money::of((string) $row['sale_price']) : null,
                stock: (int) $row['stock'],
                isActive: ! array_key_exists('is_active', $row) || (bool) $row['is_active'],
            );
        }

        return new OwnProductData(
            name: (array) $this->validated('name'),
            label: (array) $this->validated('label', []),
            summary: (array) $this->validated('summary'),
            description: (array) $this->validated('description'),
            sku: $this->validated('sku'),
            slug: $this->validated('slug'),
            weightGrams: (int) $this->validated('weight_grams'),
            countryIds: $this->boolean('all_countries') ? null : array_values(array_map('intval', (array) $this->validated('country_ids', []))),
            categoryIds: array_values(array_map('intval', (array) $this->validated('category_ids'))),
            price: Money::of((string) $this->validated('price')),
            salePrice: $this->filled('sale_price') ? Money::of((string) $this->validated('sale_price')) : null,
            costPrice: $this->filled('cost_price') ? Money::of((string) $this->validated('cost_price')) : null,
            stock: (int) $this->validated('stock', 0),
            isActive: $this->boolean('is_active'),
            isFeatured: $this->boolean('is_featured'),
            manageStock: $this->boolean('manage_stock'),
            isTaxable: $this->boolean('is_taxable'),
            variants: $variants,
            images: array_values(array_filter((array) $this->file('images', []), static fn (mixed $file): bool => $file instanceof UploadedFile)),
            keepImageIds: array_values(array_map('intval', (array) $this->validated('images_keep', []))),
            storeCategoryIds: array_values(array_map('intval', (array) $this->validated('store_category_ids', []))),
        );
    }

    /**
     * @return list<int>
     */
    private function storeCountryIds(): array
    {
        $tenant = tenant();

        return $tenant !== null ? array_map('intval', (array) $tenant->getAttribute('selling_country_ids')) : [];
    }
}
