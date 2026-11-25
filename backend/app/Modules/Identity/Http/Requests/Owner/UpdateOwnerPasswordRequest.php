<?php

namespace App\Modules\Identity\Http\Requests\Owner;

use App\Modules\Identity\Models\Owner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Owner password (OWN-006): the current password is required when one is set (an
 * owner who only used Google / Apple sets a first password); at least 8 characters
 * with letters and a number, confirmed.
 */
class UpdateOwnerPasswordRequest extends FormRequest
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
        $owner = $this->user('owner');
        $hasPassword = $owner instanceof Owner && $owner->password !== null;

        return [
            'current_password' => $hasPassword ? ['required', 'string', 'current_password:owner'] : ['prohibited'],
            'password' => ['required', 'string', 'max:191', 'confirmed', Password::min(8)->letters()->numbers(), ...($hasPassword ? ['different:current_password'] : [])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('identity::owner_profile.fields.current_password'),
            'password' => __('identity::owner_profile.fields.new_password'),
        ];
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
