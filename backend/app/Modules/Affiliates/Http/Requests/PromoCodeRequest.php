<?php

namespace App\Modules\Affiliates\Http\Requests;

use App\Modules\Affiliates\DTOs\PromoCodeData;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * ADM-063 promo code create / edit: affiliate, code, discount, valid until, optional own
 * commission, minimum spend, usage limit, active.
 */
class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('promoCode');
        $percent = static fn (?string $type): string => $type === RateType::Percentage->value ? 'max:100' : 'max:1000000';

        return [
            'affiliate_id' => ['required', 'integer', Rule::exists('affiliates', 'id')],
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('affiliate_promo_codes', 'code')->ignore(is_numeric($current) ? (int) $current : ($current instanceof AffiliatePromoCode ? $current->id : null))],
            'discount_type' => ['required', Rule::enum(RateType::class)],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', $percent($this->input('discount_type'))],
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'commission_type' => ['nullable', Rule::enum(RateType::class)],
            'commission_value' => ['nullable', 'required_with:commission_type', 'numeric', 'min:0', 'decimal:0,2', $percent($this->input('commission_type'))],
            'min_spend' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = ['affiliate_id', 'code', 'discount_type', 'discount_value', 'ends_at', 'commission_type', 'commission_value', 'min_spend', 'max_uses', 'is_active'];

        return array_combine($fields, array_map(static fn (string $field): string => (string) __('affiliates::admin.promo.fields.'.$field), $fields));
    }

    public function toData(): PromoCodeData
    {
        $commissionType = $this->filled('commission_type') ? RateType::from((string) $this->validated('commission_type')) : null;

        return new PromoCodeData(
            affiliateId: (int) $this->validated('affiliate_id'),
            code: (string) $this->validated('code'),
            discountType: RateType::from((string) $this->validated('discount_type')),
            discountValue: (int) round((float) $this->validated('discount_value') * 100),
            endsAt: Carbon::createFromFormat('Y-m-d', (string) $this->validated('ends_at'))->startOfDay(),
            commissionType: $commissionType,
            commissionValue: $commissionType !== null ? (int) round((float) $this->validated('commission_value') * 100) : null,
            minSpend: $this->filled('min_spend') ? Money::of((string) $this->validated('min_spend'))->minor : 0,
            maxUses: $this->filled('max_uses') ? (int) $this->validated('max_uses') : null,
            isActive: $this->boolean('is_active'),
        );
    }
}
