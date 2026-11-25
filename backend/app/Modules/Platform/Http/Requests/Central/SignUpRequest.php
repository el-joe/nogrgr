<?php

namespace App\Modules\Platform\Http\Requests\Central;

use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Sign-up (WEB-016): e-mail, phone (intl-tel-input, E.164) and the compliance accept.
 * "Sign up with Google / Apple" submits the same form with `provider`: the e-mail then
 * comes from the provider, phone and accept are kept for when the visitor returns.
 */
class SignUpRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }

        $this->normalizePhoneInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', Rule::in(array_map(static fn (SocialProvider $provider): string => $provider->value, SocialProvider::enabled()))],
            'email' => ['required_without:provider', 'nullable', 'string', 'email:rfc', 'max:191'],
            'phone_country' => ['required', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['required', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'compliance' => ['accepted'],
        ];
    }

    public function socialProvider(): ?SocialProvider
    {
        return SocialProvider::tryFrom((string) $this->validated('provider'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('platform::signup.form.email'),
            'phone' => __('platform::signup.form.phone'),
            'phone_country' => __('platform::signup.form.phone'),
            'compliance' => __('platform::signup.form.compliance_attribute'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['compliance.accepted' => __('platform::signup.form.compliance_required')];
    }
}
