<?php

namespace App\Modules\Platform\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Live store-address check (GET register/availability): ?slug= or ?domain= plus the
 * registration token; check=dns also looks up the custom domain's DNS records.
 */
class RegistrationAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => StoreAddressCheck::normalizeSlug($this->query('slug')),
            'domain' => StoreAddressCheck::normalizeDomain($this->query('domain')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'slug' => ['nullable', 'required_without:domain', 'string', 'max:63'],
            'domain' => ['nullable', 'required_without:slug', 'string', 'max:253'],
            'check' => ['nullable', 'in:dns'],
            'for' => ['nullable', 'string', 'max:63'],
        ];
    }
}
