<?php

namespace App\Modules\Localization\Http\Requests\Vendor;

use App\Modules\Localization\DTOs\StoreLanguageSettingsData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vendor › Settings › Languages save (VEN-073): default, switched-on codes, order.
 */
class UpdateStoreLanguagesRequest extends FormRequest
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
            'default' => ['required', 'string', 'max:12', 'alpha_dash:ascii'],
            'enabled' => ['nullable', 'array', 'max:200'],
            'enabled.*' => ['string', 'max:12', 'alpha_dash:ascii'],
            'order' => ['nullable', 'array', 'max:200'],
            'order.*' => ['string', 'max:12', 'alpha_dash:ascii'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default' => (string) __('localization::vendor_languages.fields.default'),
            'enabled' => (string) __('localization::vendor_languages.fields.enabled'),
            'order' => (string) __('localization::vendor_languages.fields.order'),
        ];
    }

    public function toData(): StoreLanguageSettingsData
    {
        $codes = static fn (mixed $value): array => array_values(array_map(static fn ($code): string => strtolower((string) $code), (array) $value));

        return new StoreLanguageSettingsData(
            strtolower((string) $this->validated('default')),
            $codes($this->validated('enabled', [])),
            $codes($this->validated('order', [])),
        );
    }
}
