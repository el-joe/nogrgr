<?php

namespace App\Modules\Commerce\Http\Requests\Vendor;

use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-041b: the store flash sale a product is added to (one of the sales that have not
 * ended).
 */
class AttachFlashSaleProductRequest extends FormRequest
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
        return ['flash_sale_id' => ['required', 'integer', Rule::in(app(VendorPromotionPageService::class)->openSales()->modelKeys())]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['flash_sale_id' => __('commerce::vendor_promotions.flash_sales.attach.sale')];
    }

    public function saleId(): int
    {
        return (int) $this->validated('flash_sale_id');
    }
}
