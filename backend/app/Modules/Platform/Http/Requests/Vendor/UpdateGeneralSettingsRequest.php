<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use App\Modules\Platform\DTOs\StoreGeneralSettingsData;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-071 Store card: name, support e-mail, default profit 0–100 % (2 decimals), timezone.
 */
class UpdateGeneralSettingsRequest extends FormRequest
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
            'store_name' => ['required', 'string', 'min:2', 'max:120'],
            'support_email' => ['nullable', 'string', 'email:rfc', 'max:191'],
            'profit_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_name' => __('platform::vendor_general.fields.store_name'),
            'support_email' => __('platform::vendor_general.fields.support_email'),
            'profit_percent' => __('platform::vendor_general.fields.profit_percent'),
            'timezone' => __('platform::vendor_general.fields.timezone'),
        ];
    }

    public function toData(): StoreGeneralSettingsData
    {
        $email = trim((string) $this->validated('support_email'));

        return new StoreGeneralSettingsData(
            storeName: trim((string) $this->validated('store_name')),
            supportEmail: $email !== '' ? strtolower($email) : null,
            profitPercentBps: (int) round(((float) $this->validated('profit_percent')) * 100),
            timezone: (string) $this->validated('timezone'),
        );
    }
}
