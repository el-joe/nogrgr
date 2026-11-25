<?php

namespace App\Modules\Localization\Http\Requests\Admin;

use App\Modules\Localization\Enums\Continent;
use App\Modules\Localization\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit of an ISO country: names, phone code, default currency, region, active.
 */
class CountryRequest extends FormRequest
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
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:100'],
            'name.ar' => ['required', 'string', 'max:100'],
            'phone_code' => ['nullable', 'string', 'regex:/^\+?[0-9]{1,4}(-[0-9]{1,4})?$/'],
            'currency_code' => ['nullable', 'string', Rule::exists(Currency::class, 'code')],
            'continent' => ['nullable', Rule::enum(Continent::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name.en' => __('localization::admin.fields.name_en'),
            'name.ar' => __('localization::admin.fields.name_ar'),
            'phone_code' => __('localization::admin.countries.fields.phone_code'),
            'currency_code' => __('localization::admin.countries.fields.currency'),
            'continent' => __('localization::admin.countries.fields.region'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            ...$this->validated(),
            'phone_code' => $this->filled('phone_code') ? (string) $this->validated('phone_code') : null,
            'currency_code' => $this->filled('currency_code') ? (string) $this->validated('currency_code') : null,
            'continent' => $this->filled('continent') ? (string) $this->validated('continent') : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
