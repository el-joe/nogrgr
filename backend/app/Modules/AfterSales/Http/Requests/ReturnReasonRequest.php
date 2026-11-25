<?php

namespace App\Modules\AfterSales\Http\Requests;

use App\Modules\AfterSales\DTOs\ReturnReasonData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ADM-090 reason modal: English + Arabic label, video required, seller fault, active, order.
 */
class ReturnReasonRequest extends FormRequest
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
            'name.en' => ['required', 'string', 'max:80'],
            'name.ar' => ['required', 'string', 'max:80'],
            'requires_video' => ['nullable', 'boolean'],
            'seller_fault' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name.en' => __('after-sales::policy.reasons.name_en'), 'name.ar' => __('after-sales::policy.reasons.name_ar')];
    }

    public function toData(): ReturnReasonData
    {
        return new ReturnReasonData(
            name: ['en' => trim((string) $this->validated('name.en')), 'ar' => trim((string) $this->validated('name.ar'))],
            requiresVideo: $this->boolean('requires_video'),
            sellerFault: $this->boolean('seller_fault'),
            isActive: $this->boolean('is_active', true),
            sortOrder: (int) ($this->validated('sort_order') ?? 0),
        );
    }
}
