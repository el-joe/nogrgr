<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\DTOs\StorePageData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store page create / edit (VEN-033a, Figma Vendor Panel 85:106165): title, body (rich
 * text) and meta description per store language (title required in ar + en), slug
 * (custom pages; system pages keep theirs), published and "show in footer".
 */
class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'show_in_footer' => $this->boolean('show_in_footer'),
            'slug' => is_string($slug) && trim($slug) !== '' ? strtolower(trim($slug)) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...app(CatalogLocales::class)->rules('title', ['string', 'max:150']),
            'body' => ['nullable', 'array'],
            'body.*' => ['nullable', 'string', 'max:100000'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_active' => ['boolean'],
            'show_in_footer' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'title' => __('content::store_pages.fields.title'),
            'slug' => __('content::store_pages.fields.slug'),
        ];

        foreach (app(CatalogLocales::class)->codes() as $code) {
            $attributes['title.'.$code] = __('content::store_pages.fields.title_locale', ['locale' => strtoupper($code)]);
            $attributes['body.'.$code] = __('content::store_pages.fields.body');
            $attributes['meta_description.'.$code] = __('content::store_pages.fields.meta_description');
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => __('content::store_pages.validation.slug')];
    }

    public function toData(): StorePageData
    {
        $locales = app(CatalogLocales::class);
        $body = [];

        foreach ((array) $this->validated('body', []) as $code => $html) {
            if (in_array($code, $locales->codes(), true) && is_string($html)) {
                $body[$code] = $html;
            }
        }

        return new StorePageData(
            title: $locales->clean((array) $this->validated('title')),
            body: $body,
            metaDescription: $locales->clean((array) $this->validated('meta_description', [])),
            slug: $this->validated('slug'),
            isActive: (bool) $this->validated('is_active'),
            showInFooter: (bool) $this->validated('show_in_footer'),
        );
    }
}
