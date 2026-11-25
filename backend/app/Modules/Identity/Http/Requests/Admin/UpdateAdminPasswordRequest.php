<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * My profile › Password: current password + new password (12+, number, symbol) confirmed.
 */
class UpdateAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:admin'],
            'password' => ['required', 'string', 'max:191', 'confirmed', 'different:current_password', AdminRequest::passwordRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('identity::profile.fields.current_password'),
            'password' => __('identity::profile.fields.new_password'),
        ];
    }
}
