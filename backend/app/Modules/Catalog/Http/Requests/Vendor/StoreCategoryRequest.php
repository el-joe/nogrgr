<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\DTOs\Vendor\StoreCategoryData;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Store-own category create / edit (VEN-017b, Tenant Panel Layout 12:17672): localized
 * name / description / meta, parent (store category), order, countries, flags, image.
 */
class StoreCategoryRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'array'],
            "name.{$fallback}" => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'array'],
            'meta_keywords' => ['nullable', 'array'],
            'meta_description' => ['nullable', 'array'],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash:ascii'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'all_countries' => ['sometimes', 'boolean'],
            'country_ids' => ['required_unless:all_countries,1,true', 'array', 'max:250'],
            'country_ids.*' => ['integer', 'min:1', Rule::in($this->storeCountryIds())],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_image' => ['sometimes', 'boolean'],
        ];

        foreach (array_keys(Locale::enabled()) as $locale) {
            $rules["name.{$locale}"] ??= ['nullable', 'string', 'max:120'];
            $rules["description.{$locale}"] = ['nullable', 'string', 'max:5000'];
            $rules["meta_keywords.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["meta_description.{$locale}"] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fallback = (string) config('app.fallback_locale', 'en');

        return [
            "name.{$fallback}" => (string) __('catalog::taxonomy.fields.name'),
            'parent_id' => (string) __('catalog::taxonomy.fields.parent'),
            'sort_order' => (string) __('catalog::taxonomy.fields.order'),
            'country_ids' => (string) __('catalog::taxonomy.fields.countries'),
            'image' => (string) __('catalog::taxonomy.fields.image'),
            'slug' => (string) __('catalog::taxonomy.fields.slug'),
        ];
    }

    public function toData(): StoreCategoryData
    {
        $image = $this->file('image');

        return new StoreCategoryData(
            name: (array) $this->validated('name'),
            slug: $this->filled('slug') ? (string) $this->validated('slug') : null,
            parentId: $this->filled('parent_id') ? (int) $this->validated('parent_id') : null,
            sortOrder: $this->filled('sort_order') ? (int) $this->validated('sort_order') : null,
            description: (array) $this->validated('description', []),
            metaKeywords: (array) $this->validated('meta_keywords', []),
            metaDescription: (array) $this->validated('meta_description', []),
            countryIds: $this->boolean('all_countries') ? null : array_values(array_map('intval', (array) $this->validated('country_ids', []))),
            isActive: $this->boolean('is_active'),
            isFeatured: $this->boolean('is_featured'),
            image: $image instanceof UploadedFile ? $image : null,
            removeImage: $this->boolean('remove_image'),
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
