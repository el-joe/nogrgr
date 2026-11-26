<?php

namespace App\Modules\AfterSales\Http\Requests\Vendor;

use App\Modules\AfterSales\DTOs\StoreReturnPolicyData;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\Catalog\Models\Category;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-079: stricter than the platform default, never looser — window 0..platform days,
 * restocking fee platform %..100 %, non-returnable categories (the platform's are always
 * kept), exchanges only when the platform allows them, policy text per enabled locale.
 */
class StoreReturnPolicyRequest extends FormRequest
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
        $platform = app(ReturnPolicyService::class)->platformPolicy();

        return [
            'window_days' => ['required', 'integer', 'min:0', 'max:'.$platform->window_days],
            'restocking_fee' => ['required', 'numeric', 'decimal:0,2', 'min:'.($platform->restocking_fee_bps / 100), 'max:100'],
            'non_returnable_category_ids' => ['nullable', 'array', 'max:200'],
            'non_returnable_category_ids.*' => ['integer', 'distinct', Rule::exists(Category::class, 'id')],
            'allow_exchanges' => ['nullable', 'boolean'],
            'text' => ['nullable', 'array'],
            'text.*' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $platform = app(ReturnPolicyService::class)->platformPolicy();

        return [
            'window_days.max' => __('after-sales::vendor_policy.errors.window', ['days' => $platform->window_days]),
            'restocking_fee.min' => __('after-sales::vendor_policy.errors.fee', ['fee' => $platform->restocking_fee_bps / 100]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'window_days' => __('after-sales::vendor_policy.fields.window'),
            'restocking_fee' => __('after-sales::vendor_policy.fields.fee'),
            'non_returnable_category_ids' => __('after-sales::vendor_policy.fields.categories'),
            'non_returnable_category_ids.*' => __('after-sales::vendor_policy.fields.categories'),
            'text.*' => __('after-sales::vendor_policy.fields.text'),
        ];
    }

    public function toData(): StoreReturnPolicyData
    {
        $text = [];

        foreach (array_keys(Locale::enabled()) as $locale) {
            $value = trim((string) ($this->validated('text')[$locale] ?? ''));

            if ($value !== '') {
                $text[$locale] = $value;
            }
        }

        return new StoreReturnPolicyData(
            windowDays: (int) $this->validated('window_days'),
            restockingFeeBps: (int) round(((float) $this->validated('restocking_fee')) * 100),
            nonReturnableCategoryIds: array_values(array_map('intval', (array) ($this->validated('non_returnable_category_ids') ?? []))),
            allowExchanges: $this->boolean('allow_exchanges'),
            text: $text,
        );
    }
}
