<?php

namespace App\Modules\Affiliates\Http\Requests;

use App\Modules\Affiliates\DTOs\AffiliateUpdateData;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Identity\Enums\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-060a: commission type (empty = program default) + value (% or USD) and status.
 */
class UpdateAffiliateRequest extends FormRequest
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
            'commission_type' => ['nullable', Rule::enum(RateType::class)],
            'commission_value' => ['nullable', 'required_with:commission_type', 'numeric', 'min:0', 'decimal:0,2',
                $this->input('commission_type') === RateType::Percentage->value ? 'max:100' : 'max:1000000'],
            'status' => ['required', Rule::enum(AccountStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'commission_type' => (string) __('affiliates::admin.accounts.fields.commission_type'),
            'commission_value' => (string) __('affiliates::admin.accounts.fields.commission_value'),
            'status' => (string) __('affiliates::admin.accounts.fields.status'),
        ];
    }

    public function toData(): AffiliateUpdateData
    {
        $type = $this->filled('commission_type') ? RateType::from((string) $this->validated('commission_type')) : null;

        return new AffiliateUpdateData(
            commissionType: $type,
            commissionValue: $type !== null ? (int) round((float) $this->validated('commission_value') * 100) : null,
            status: AccountStatus::from((string) $this->validated('status')),
        );
    }
}
