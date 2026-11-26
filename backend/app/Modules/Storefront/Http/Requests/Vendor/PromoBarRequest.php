<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\DTOs\Appearance\PromoBarData;
use App\Modules\Storefront\Support\HexColor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Appearance › Promo bar (VEN-034c): on / off, message per language (English required
 * when shown), optional link (a store path or http(s) URL), background colour.
 */
class PromoBarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $link = $this->input('link');
        $background = $this->input('background');

        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'link' => is_string($link) && trim($link) !== '' ? trim($link) : null,
            'background' => is_string($background) && trim($background) !== '' ? strtolower(trim($background)) : PromoBarData::DEFAULT_BACKGROUND,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'text' => ['nullable', 'array'],
            'text.en' => ['nullable', 'required_if_accepted:enabled', 'string', 'max:160'],
            'text.*' => ['nullable', 'string', 'max:160'],
            'link' => ['nullable', 'string', 'max:500', 'regex:#^(/[^\s]*|https?://[^\s]+)$#i'],
            'background' => ['required', 'string', 'regex:'.HexColor::PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'text.en' => __('content::store_appearance.promo.text_locale', ['locale' => 'EN']),
            'text.*' => __('content::store_appearance.promo.text'),
            'link' => __('content::store_appearance.promo.link'),
            'background' => __('content::store_appearance.promo.background'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link.regex' => __('content::store_appearance.validation.link'),
            'background.regex' => __('content::store_appearance.validation.color'),
            'text.en.required_if_accepted' => __('content::store_appearance.validation.promo_text'),
        ];
    }

    public function toData(): PromoBarData
    {
        return new PromoBarData(
            enabled: (bool) $this->validated('enabled'),
            text: app(CatalogLocales::class)->clean((array) $this->validated('text', [])),
            link: $this->validated('link'),
            background: (string) $this->validated('background'),
        );
    }
}
