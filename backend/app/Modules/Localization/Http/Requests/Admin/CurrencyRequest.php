<?php

namespace App\Modules\Localization\Http\Requests\Admin;

use App\Modules\Localization\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (code required, unique ISO 4217) and edit (code fixed) of a currency.
 * `rate` = units per 1 USD, optional manual override until the next sync.
 */
class CurrencyRequest extends FormRequest
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
        $creating = ! $this->route('currency') instanceof Currency;

        return [
            'code' => $creating
                ? ['required', 'string', 'size:3', 'alpha', Rule::unique(Currency::class, 'code')]
                : ['prohibited'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:100'],
            'name.ar' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:16'],
            'decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'rate' => [$creating ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:99999999999999'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('localization::admin.currencies.fields.code'),
            'name.en' => __('localization::admin.fields.name_en'),
            'name.ar' => __('localization::admin.fields.name_ar'),
            'symbol' => __('localization::admin.currencies.fields.symbol'),
            'decimals' => __('localization::admin.currencies.fields.decimals'),
            'rate' => __('localization::admin.currencies.fields.rate'),
            'sort_order' => __('localization::admin.fields.sort_order'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $currency = $this->route('currency');

        return [
            ...$this->validated(),
            'code' => $currency instanceof Currency ? $currency->code : strtoupper((string) $this->validated('code')),
            'decimals' => (int) $this->validated('decimals'),
            'rate' => $this->filled('rate') ? (string) $this->validated('rate') : null,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }
}
