<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\FixedShippingCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fixed cost create / edit (ADM-031a): one rate per country (USD per gram, ≤ 6 decimals).
 */
class FixedShippingCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('price_per_gram'))) {
            $this->merge(['price_per_gram' => trim(ltrim(trim((string) $this->input('price_per_gram')), '$'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cost = $this->route('fixedCost');

        return [
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id'), Rule::unique(FixedShippingCost::class, 'country_id')->ignore($cost instanceof FixedShippingCost ? $cost->id : null)],
            'price_per_gram' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['country_id.unique' => __('shipping::fixed_costs.country_taken')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_id' => __('shipping::fixed_costs.fields.country'),
            'price_per_gram' => __('shipping::fixed_costs.fields.rate'),
        ];
    }

    /**
     * @return array{country_id: int, price_per_gram: string}
     */
    public function payload(): array
    {
        return [
            'country_id' => (int) $this->validated('country_id'),
            'price_per_gram' => number_format((float) $this->validated('price_per_gram'), 6, '.', ''),
        ];
    }
}
