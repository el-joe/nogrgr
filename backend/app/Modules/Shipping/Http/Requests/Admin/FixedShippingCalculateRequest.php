<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fixed-cost calculator (Figma 71:42662): country, product weight (g), sale price (USD).
 */
class FixedShippingCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['sale_price', 'weight_grams'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => str_replace([',', '$', ' '], '', (string) $this->input($key))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            'weight_grams' => ['required', 'integer', 'min:0', 'max:10000000'],
            'sale_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
        ];
    }

    public function salePrice(): Money
    {
        return Money::of((string) $this->validated('sale_price'));
    }
}
