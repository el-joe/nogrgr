<?php

namespace App\Modules\Affiliates\Http\Requests;

use App\Modules\Affiliates\DTOs\ProgramSettingsData;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ADM-060c: cookie days, default commission (%) and minimum payout (USD).
 */
class ProgramSettingsRequest extends FormRequest
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
            'cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_commission' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'min_payout' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cookie_days' => (string) __('affiliates::admin.settings.cookie_days'),
            'default_commission' => (string) __('affiliates::admin.settings.default_commission'),
            'min_payout' => (string) __('affiliates::admin.settings.min_payout'),
        ];
    }

    public function toData(): ProgramSettingsData
    {
        return new ProgramSettingsData(
            cookieDays: (int) $this->validated('cookie_days'),
            defaultCommissionBps: (int) round((float) $this->validated('default_commission') * 100),
            minPayoutCents: Money::of((string) $this->validated('min_payout'))->minor,
        );
    }
}
