<?php

namespace App\Modules\Identity\Http\Requests\Vendor;

use App\Modules\Identity\DTOs\VendorStaffData;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Vendor › Settings › Staff invite / edit (VEN-076a): name, e-mail (unique in the
 * store), optional phone (intl-tel-input → E.164) and one store role — never the
 * built-in "owner". Authorisation is the route's `settings.staff.manage` permission.
 */
class StaffRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone')) && trim($this->input('phone')) === '') {
            $this->merge(['phone' => null]);
        }

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
        $id = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(VendorStaff::class, 'email')->ignore(is_numeric($id) ? (int) $id : null)],
            'phone_country' => ['nullable', 'required_with:phone', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'role' => [
                'required', 'string',
                Rule::exists((string) config('permission.table_names.roles'), 'name')->where('guard_name', Guard::Vendor->value),
                Rule::notIn([SystemRole::VENDOR_OWNER]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('identity::staff.fields.name'),
            'email' => __('identity::staff.fields.email'),
            'phone' => __('identity::staff.fields.phone'),
            'phone_country' => __('identity::staff.fields.phone'),
            'role' => __('identity::staff.fields.role'),
        ];
    }

    public function toData(): VendorStaffData
    {
        $phone = $this->validated('phone');

        return new VendorStaffData(
            name: trim((string) $this->validated('name')),
            email: (string) $this->validated('email'),
            role: (string) $this->validated('role'),
            phone: is_string($phone) && $phone !== '' ? $phone : null,
        );
    }
}
