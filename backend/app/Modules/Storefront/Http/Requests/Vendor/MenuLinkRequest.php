<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\DTOs\Appearance\MenuLinkData;
use App\Modules\Storefront\Enums\FooterColumn;
use App\Modules\Storefront\Enums\MenuLinkPlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Header / footer link create / edit (VEN-034d/e): label per language (ar + en
 * required), URL (a store path or http(s) address), footer column for footer links, on.
 */
class MenuLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('url');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'url' => is_string($url) ? trim($url) : $url,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...app(CatalogLocales::class)->rules('label', ['string', 'max:60']),
            'placement' => ['required', Rule::enum(MenuLinkPlacement::class)],
            'footer_column' => ['nullable', 'required_if:placement,footer', Rule::enum(FooterColumn::class)],
            'url' => ['required', 'string', 'max:500', 'regex:#^(/[^\s]*|https?://[^\s]+)$#i'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'label' => __('content::store_appearance.links.label'),
            'url' => __('content::store_appearance.links.url'),
            'footer_column' => __('content::store_appearance.links.column'),
        ];

        foreach (app(CatalogLocales::class)->codes() as $code) {
            $attributes['label.'.$code] = __('content::store_appearance.links.label_locale', ['locale' => strtoupper($code)]);
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['url.regex' => __('content::store_appearance.validation.link')];
    }

    public function toData(): MenuLinkData
    {
        $placement = MenuLinkPlacement::from((string) $this->validated('placement'));

        return new MenuLinkData(
            placement: $placement,
            label: app(CatalogLocales::class)->clean((array) $this->validated('label')),
            url: (string) $this->validated('url'),
            footerColumn: $placement === MenuLinkPlacement::Footer ? FooterColumn::tryFrom((string) $this->validated('footer_column')) : null,
            isActive: (bool) $this->validated('is_active'),
        );
    }
}
