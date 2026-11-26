<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\DTOs\Appearance\FooterSettingsData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Appearance › Footer (VEN-034e): about text and copyright line per language (empty =
 * theme default; ":year" becomes the current year) and the social icons switch.
 */
class FooterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['show_social' => $this->boolean('show_social')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'about' => ['nullable', 'array'],
            'about.*' => ['nullable', 'string', 'max:300'],
            'copyright' => ['nullable', 'array'],
            'copyright.*' => ['nullable', 'string', 'max:160'],
            'show_social' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'about.*' => __('content::store_appearance.footer.about'),
            'copyright.*' => __('content::store_appearance.footer.copyright'),
        ];
    }

    public function toData(): FooterSettingsData
    {
        $locales = app(CatalogLocales::class);

        return new FooterSettingsData(
            about: $locales->clean((array) $this->validated('about', [])),
            copyright: $locales->clean((array) $this->validated('copyright', [])),
            showSocial: (bool) $this->validated('show_social'),
        );
    }
}
