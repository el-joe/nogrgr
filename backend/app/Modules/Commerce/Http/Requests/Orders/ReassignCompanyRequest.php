<?php

namespace App\Modules\Commerce\Http\Requests\Orders;

use App\Modules\Shipping\Models\ShippingCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-021d: an active shipping company.
 */
class ReassignCompanyRequest extends FormRequest
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
        return ['shipping_company_id' => ['required', 'integer', Rule::exists(ShippingCompany::class, 'id')->where('is_active', true)]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['shipping_company_id' => __('commerce::fulfilment.fields.company')];
    }

    public function companyId(): int
    {
        return (int) $this->validated('shipping_company_id');
    }
}
