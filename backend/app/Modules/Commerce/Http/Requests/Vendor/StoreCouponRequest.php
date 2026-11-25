<?php

namespace App\Modules\Commerce\Http\Requests\Vendor;

use App\Modules\Commerce\DTOs\CouponData;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Services\Promotions\StoreCouponService;
use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store coupon create / edit (VEN-040a, Figma 87:111915): code (unique in the store, also
 * against deleted coupons), type, value (% or USD), max discount (%), min spend, usage
 * limits, first order only, dates, countries (the store's selling countries; none = all),
 * active. Amounts arrive as USD decimals ("$30.00" accepted) and leave as minor units.
 */
class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = static fn (mixed $value): mixed => is_string($value) ? trim(str_replace([',', '$', '%'], '', $value)) : $value;

        $this->merge([
            'code' => is_string($this->input('code')) ? strtoupper(trim((string) $this->input('code'))) : $this->input('code'),
            'value' => $clean($this->input('value')),
            'max_discount' => $clean($this->input('max_discount')),
            'min_spend' => $clean($this->input('min_spend')),
            'usage_limit' => $clean($this->input('usage_limit')),
            'per_customer_limit' => $clean($this->input('per_customer_limit')),
            'is_active' => $this->boolean('is_active'),
            'first_order_only' => $this->boolean('first_order_only'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignore = filter_var($this->route('coupon'), FILTER_VALIDATE_INT);
        $ignore = $ignore === false ? null : $ignore;

        return [
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
                if (is_string($value) && app(StoreCouponService::class)->codeTaken($value, $ignore)) {
                    $fail(__('commerce::coupons.code_taken'));
                }
            }],
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['exclude_if:type,'.CouponType::FreeShipping->value, 'required', 'numeric', 'decimal:0,2', 'min:0.01', Rule::when($this->input('type') === CouponType::Percentage->value, ['max:100'], ['max:1000000'])],
            'max_discount' => ['nullable', 'exclude_unless:type,'.CouponType::Percentage->value, 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'min_spend' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:100000', Rule::when($this->filled('usage_limit') && is_numeric($this->input('usage_limit')), ['lte:usage_limit'])],
            'first_order_only' => ['boolean'],
            'dates_from' => ['required', 'date_format:Y-m-d'],
            'dates_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:dates_from'],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', 'distinct', Rule::in(array_keys(app(VendorPromotionPageService::class)->countries()))],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('commerce::coupons.fields.code'),
            'name' => __('commerce::coupons.fields.name'),
            'type' => __('commerce::coupons.fields.type'),
            'value' => __('commerce::coupons.fields.value'),
            'max_discount' => __('commerce::coupons.fields.max_discount'),
            'min_spend' => __('commerce::coupons.fields.min_spend'),
            'usage_limit' => __('commerce::coupons.fields.usage_limit'),
            'per_customer_limit' => __('commerce::coupons.fields.per_customer_limit'),
            'dates_from' => __('commerce::coupons.fields.starts'),
            'dates_to' => __('commerce::coupons.fields.ends'),
            'country_ids' => __('commerce::coupons.fields.countries'),
            'country_ids.*' => __('commerce::coupons.fields.countries'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => __('commerce::coupons.code_format')];
    }

    public function toData(): CouponData
    {
        $type = CouponType::from((string) $this->validated('type'));
        $minor = static fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) round(((float) $value) * 100);

        return new CouponData(
            code: (string) $this->validated('code'),
            type: $type,
            percentage: $type === CouponType::Percentage ? (float) $this->validated('value') : null,
            amountMinor: $type === CouponType::Fixed ? $minor($this->validated('value')) : null,
            maxDiscountMinor: $minor($this->validated('max_discount')),
            minSpendMinor: $minor($this->validated('min_spend')) ?? 0,
            usageLimit: $this->filled('usage_limit') ? (int) $this->validated('usage_limit') : null,
            perCustomerLimit: $this->filled('per_customer_limit') ? (int) $this->validated('per_customer_limit') : null,
            firstOrderOnly: (bool) $this->validated('first_order_only'),
            startsAt: CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('dates_from'))?->startOfDay(),
            endsAt: CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('dates_to'))?->endOfDay(),
            isActive: (bool) $this->validated('is_active'),
            name: $this->filled('name') ? trim((string) $this->validated('name')) : null,
            countryIds: array_values(array_map('intval', (array) $this->validated('country_ids', []))),
        );
    }
}
