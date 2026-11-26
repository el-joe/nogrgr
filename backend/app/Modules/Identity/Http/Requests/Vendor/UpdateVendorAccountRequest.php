<?php

namespace App\Modules\Identity\Http\Requests\Vendor;

use App\Modules\Identity\DTOs\VendorAccountData;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * VEN-070 Profile card: name, e-mail (unique in the store; ignored for the owner),
 * phone (intl-tel-input → E.164, required by the design) and panel language.
 */
class UpdateVendorAccountRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return $this->user('vendor') instanceof VendorStaff;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staff = $this->staff();
        $owner = (bool) ($staff->getAttributes()['is_owner'] ?? false);

        return [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'email' => $owner ? ['nullable'] : ['required', 'string', 'email', 'max:191', Rule::unique(VendorStaff::class, 'email')->ignore($staff->id)],
            'phone_country' => ['required', 'string', 'size:2', $this->phoneRegionRule()],
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
            'name' => __('identity::vendor_account.fields.name'),
            'email' => __('identity::vendor_account.fields.email'),
            'phone' => __('identity::vendor_account.fields.phone'),
            'phone_country' => __('identity::vendor_account.fields.phone'),
            'locale' => __('identity::vendor_account.fields.language'),
        ];
    }

    public function toData(): VendorAccountData
    {
        $owner = (bool) ($this->staff()->getAttributes()['is_owner'] ?? false);

        return new VendorAccountData(
            name: trim((string) $this->validated('name')),
            email: $owner ? null : (string) $this->validated('email'),
            phone: (string) $this->validated('phone'),
            locale: (string) $this->validated('locale'),
        );
    }

    private function staff(): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $this->user('vendor');

        return $staff;
    }
}
