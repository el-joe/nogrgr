<?php

namespace App\Modules\Localization\Http\Requests\Vendor;

use App\Modules\Localization\DTOs\StoreCurrencySettingsData;
use App\Modules\Localization\Services\StoreCurrencyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-072: active[] / default[] currency codes, each one of the store's configurable rows.
 */
class UpdateStoreCurrenciesRequest extends FormRequest
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
        $codes = app(StoreCurrencyService::class)->configurableCodes();

        return [
            'active' => ['nullable', 'array'],
            'active.*' => ['string', 'distinct', Rule::in($codes)],
            'default' => ['nullable', 'array'],
            'default.*' => ['string', 'distinct', Rule::in($codes)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'active.*' => __('localization::vendor_currencies.columns.active'),
            'default.*' => __('localization::vendor_currencies.columns.default'),
        ];
    }

    public function toData(): StoreCurrencySettingsData
    {
        return new StoreCurrencySettingsData(
            active: array_values(array_map('strval', (array) ($this->validated('active') ?? []))),
            default: array_values(array_map('strval', (array) ($this->validated('default') ?? []))),
        );
    }
}
