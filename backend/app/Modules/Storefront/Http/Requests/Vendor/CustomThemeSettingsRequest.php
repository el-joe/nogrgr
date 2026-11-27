<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * VEN-036b theme settings: `settings[<id>]` values; each is checked against the live
 * version's theme.json schema by CustomThemeService::saveSettings (types differ per theme).
 */
class CustomThemeSettingsRequest extends FormRequest
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
            'settings' => ['nullable', 'array', 'max:60'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return (array) ($this->validated('settings') ?? []);
    }
}
