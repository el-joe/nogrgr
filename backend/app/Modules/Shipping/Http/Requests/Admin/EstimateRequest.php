<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\ShippingCompany;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Delivery estimator preview (ADM-033d): country + order date (+ company) → date range.
 */
class EstimateRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'company_id' => ['nullable', 'integer', Rule::exists(ShippingCompany::class, 'id')],
        ];
    }

    public function orderDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('date'))->startOfDay();
    }
}
