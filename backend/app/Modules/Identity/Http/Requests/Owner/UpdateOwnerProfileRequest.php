<?php

namespace App\Modules\Identity\Http\Requests\Owner;

use App\Modules\Identity\DTOs\OwnerProfileData;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Owner profile (OWN-006): name, phone (intl-tel-input number + ISO2 country) and
 * language. The e-mail identifies the owner in every store and is not edited here.
 */
class UpdateOwnerProfileRequest extends FormRequest
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

        $this->normalizePhoneInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone_country' => ['required_with:phone', 'nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['required', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'locale' => ['required', 'string', Rule::in(array_keys(Locale::enabled()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('identity::owner_profile.fields.name'),
            'phone' => __('identity::owner_profile.fields.phone'),
            'phone_country' => __('identity::owner_profile.fields.phone'),
            'locale' => __('identity::owner_profile.fields.language'),
        ];
    }

    public function toData(): OwnerProfileData
    {
        return new OwnerProfileData(
            name: (string) $this->validated('name'),
            phone: $this->validated('phone'),
            locale: (string) $this->validated('locale'),
        );
    }
}
