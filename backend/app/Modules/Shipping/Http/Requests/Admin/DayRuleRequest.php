<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Models\ShippingDayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Day rule of a company (ADM-033b): country (one row per company), min / max days, default carrier.
 */
class DayRuleRequest extends FormRequest
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
        $company = $this->route('company');
        $rule = $this->route('dayRule');

        return [
            'country_id' => [
                'required', 'integer', Rule::exists(Country::class, 'id'),
                Rule::unique(ShippingDayRule::class, 'country_id')
                    ->where('shipping_company_id', $company instanceof ShippingCompany ? $company->id : (int) $company)
                    ->ignore(is_numeric($rule) ? (int) $rule : null),
            ],
            'min_days' => ['required', 'integer', 'min:0', 'max:60'],
            'max_days' => ['required', 'integer', 'min:1', 'max:90', 'gte:min_days'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['country_id.unique' => __('shipping::companies.day_rules.duplicate')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_id' => __('shipping::companies.day_rules.country'),
            'min_days' => __('shipping::companies.day_rules.min_days'),
            'max_days' => __('shipping::companies.day_rules.max_days'),
        ];
    }

    /**
     * @return array{country_id: int, min_days: int, max_days: int, is_default: bool}
     */
    public function payload(): array
    {
        return [
            'country_id' => (int) $this->validated('country_id'),
            'min_days' => (int) $this->validated('min_days'),
            'max_days' => (int) $this->validated('max_days'),
            'is_default' => $this->boolean('is_default'),
        ];
    }
}
