<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Vendor sign-in on the store host (`/login`) and the central host (`/tenant/login`).
 */
class VendorLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:191'],
            'remember' => ['sometimes', 'boolean'],
            'store' => ['sometimes', 'nullable', 'string', 'max:63'],
        ];
    }
}
