<?php

namespace App\Modules\Affiliates\Http\Requests\Portal;

use App\Modules\Affiliates\DTOs\AffiliateProfileData;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * AFF-013 profile: name, phone (optional), panel language. The e-mail is the sign-in and
 * is not edited here.
 */
class UpdateProfileRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        if (blank($this->input('phone'))) {
            $this->merge(['phone' => null, 'phone_country' => null]);
        }

        $this->normalizePhoneInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone_country' => ['nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'locale' => ['required', 'string', Rule::in(array_keys(Locale::enabled()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('affiliates::panel.profile.name'),
            'phone' => (string) __('affiliates::panel.profile.phone'),
            'phone_country' => (string) __('affiliates::panel.profile.phone'),
            'locale' => (string) __('affiliates::panel.profile.language'),
        ];
    }

    public function toData(): AffiliateProfileData
    {
        return new AffiliateProfileData(
            name: (string) $this->validated('name'),
            phone: $this->validated('phone'),
            locale: (string) $this->validated('locale'),
        );
    }
}
