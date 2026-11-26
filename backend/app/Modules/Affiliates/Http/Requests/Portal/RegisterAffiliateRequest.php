<?php

namespace App\Modules\Affiliates\Http\Requests\Portal;

use App\Modules\Affiliates\DTOs\AffiliateRegistrationData;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * AFF-002 partner sign-up: name, e-mail (unique among affiliates), phone, password,
 * accepted program terms.
 */
class RegisterAffiliateRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
        ]);

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
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique((new Affiliate)->getConnectionName().'.affiliates', 'email')],
            'phone_country' => ['nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'password' => ['required', 'string', 'max:191', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('affiliates::panel.register.name'),
            'email' => (string) __('affiliates::panel.register.email'),
            'phone' => (string) __('affiliates::panel.register.phone'),
            'phone_country' => (string) __('affiliates::panel.register.phone'),
            'password' => (string) __('affiliates::panel.register.password'),
            'terms' => (string) __('affiliates::panel.register.terms_attribute'),
        ];
    }

    public function toData(): AffiliateRegistrationData
    {
        $locale = app()->getLocale();

        return new AffiliateRegistrationData(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            phone: $this->validated('phone'),
            password: (string) $this->validated('password'),
            locale: array_key_exists($locale, Locale::enabled()) ? $locale : (string) config('app.locale'),
        );
    }
}
