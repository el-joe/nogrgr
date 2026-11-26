<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Storefront\DTOs\Appearance\AppearanceColorsData;
use App\Modules\Storefront\Enums\StoreFont;
use App\Modules\Storefront\Support\HexColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Appearance › Colours & fonts (VEN-034b): brand colour (#rrggbb, empty = theme
 * default) and body fonts for LTR / RTL pages (empty = theme default).
 */
class AppearanceColorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $primary = $this->input('primary');

        $this->merge([
            'primary' => is_string($primary) && trim($primary) !== '' ? strtolower(trim($primary)) : null,
            'font_ltr' => $this->filled('font_ltr') ? $this->input('font_ltr') : null,
            'font_rtl' => $this->filled('font_rtl') ? $this->input('font_rtl') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'primary' => ['nullable', 'string', 'regex:'.HexColor::PATTERN],
            'font_ltr' => ['nullable', Rule::in(array_keys(StoreFont::ltrOptions()))],
            'font_rtl' => ['nullable', Rule::in(array_keys(StoreFont::rtlOptions()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'primary' => __('content::store_appearance.colors.primary'),
            'font_ltr' => __('content::store_appearance.colors.font_ltr'),
            'font_rtl' => __('content::store_appearance.colors.font_rtl'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['primary.regex' => __('content::store_appearance.validation.color')];
    }

    public function toData(): AppearanceColorsData
    {
        return new AppearanceColorsData(
            primary: $this->validated('primary'),
            fontLtr: StoreFont::tryFrom((string) $this->validated('font_ltr')),
            fontRtl: StoreFont::tryFrom((string) $this->validated('font_rtl')),
        );
    }
}
