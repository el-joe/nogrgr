<?php

namespace App\Modules\Identity\Http\Requests\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * VEN-070 Password card: current password, new password (8+, letters and numbers, like
 * the owner portal) and its confirmation.
 */
class UpdateVendorPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('vendor') instanceof VendorStaff;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:vendor'],
            'password' => ['required', 'string', 'max:191', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('identity::vendor_account.fields.current_password'),
            'password' => __('identity::vendor_account.fields.new_password'),
        ];
    }
}
