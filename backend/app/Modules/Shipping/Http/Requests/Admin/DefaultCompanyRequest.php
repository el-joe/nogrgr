<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\ShippingCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Default company per country (ADM-034): one active company per country.
 */
class DefaultCompanyRequest extends FormRequest
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
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            'shipping_company_id' => ['required', 'integer', Rule::exists(ShippingCompany::class, 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_id' => __('shipping::settings.defaults.country'),
            'shipping_company_id' => __('shipping::settings.defaults.company'),
        ];
    }
}
