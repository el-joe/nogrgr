<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Vendor profile › Profit & commission: vendor profit % and affiliate commission % on
 * the first payment (0–100, two decimals), stored as basis points.
 */
class VendorCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('vendors.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profit_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'affiliate_commission_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'profit_percent' => __('platform::vendors.fields.profit_percent'),
            'affiliate_commission_percent' => __('platform::vendors.fields.affiliate_commission'),
        ];
    }

    /**
     * Input for VendorCommissionData.
     *
     * @return array{profit_percent_bps: int, affiliate_commission_bps: int}
     */
    public function payload(): array
    {
        return [
            'profit_percent_bps' => VendorCreateRequest::toBps($this->validated('profit_percent')),
            'affiliate_commission_bps' => VendorCreateRequest::toBps($this->validated('affiliate_commission_percent')),
        ];
    }
}
