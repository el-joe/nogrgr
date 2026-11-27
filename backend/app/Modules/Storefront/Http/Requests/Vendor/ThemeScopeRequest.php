<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Theme of one scope (VEN-030b, Themes by country): {theme: catalogue theme id}. Whether
 * the theme is listed for the store and allowed in that country is checked by
 * ThemePageService.
 */
class ThemeScopeRequest extends FormRequest
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
        return ['theme' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'theme.required' => __('content::vendor_themes.errors.theme_required'),
            'theme.integer' => __('content::vendor_themes.errors.theme_unavailable'),
            'theme.min' => __('content::vendor_themes.errors.theme_unavailable'),
        ];
    }

    public function themeId(): int
    {
        return (int) $this->validated('theme');
    }
}
