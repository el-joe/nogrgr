<?php

namespace App\Modules\AfterSales\Http\Requests;

use App\Modules\AfterSales\DTOs\ReturnPolicyData;
use App\Modules\AfterSales\Enums\ReturnShippingPayer;
use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-090 rules: window 0–365 days, restocking fee 0–100 %, payer, categories, switches.
 */
class ReturnPolicyRequest extends FormRequest
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
            'window_days' => ['required', 'integer', 'min:0', 'max:365'],
            'restocking_fee' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'return_shipping_payer' => ['required', Rule::enum(ReturnShippingPayer::class)],
            'non_returnable_category_ids' => ['nullable', 'array'],
            'non_returnable_category_ids.*' => ['integer', Rule::exists(Category::class, 'id')],
            'require_video' => ['nullable', 'boolean'],
            'allow_exchanges' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'window_days' => __('after-sales::policy.fields.window'),
            'restocking_fee' => __('after-sales::policy.fields.fee'),
            'return_shipping_payer' => __('after-sales::policy.fields.payer'),
            'non_returnable_category_ids' => __('after-sales::policy.fields.categories'),
        ];
    }

    public function toData(): ReturnPolicyData
    {
        return new ReturnPolicyData(
            windowDays: (int) $this->validated('window_days'),
            restockingFeeBps: (int) round(((float) $this->validated('restocking_fee')) * 100),
            returnShippingPayer: ReturnShippingPayer::from((string) $this->validated('return_shipping_payer')),
            nonReturnableCategoryIds: array_values(array_map('intval', (array) ($this->validated('non_returnable_category_ids') ?? []))),
            requireVideo: $this->boolean('require_video'),
            allowExchanges: $this->boolean('allow_exchanges'),
        );
    }
}
