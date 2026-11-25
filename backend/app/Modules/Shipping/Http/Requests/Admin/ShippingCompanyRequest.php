<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Shipping\Models\ShippingCompany;
use App\Shared\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Propaganistas\LaravelPhone\Rules\Phone as PhoneRule;

/**
 * Company create / edit (ADM-033a): name, logo (one image), contact e-mail and phone
 * (intl-tel-input, E.164), website, tracking URL pattern with the {tracking} placeholder,
 * active.
 */
class ShippingCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $country = $this->input('contact_phone_country');
        $phone = $this->input('contact_phone');

        if (is_string($country)) {
            $this->merge(['contact_phone_country' => strtoupper(trim($country))]);
        }

        if (is_string($phone) && trim($phone) !== '') {
            $this->merge(['contact_phone' => Phone::e164($phone, is_string($country) ? strtoupper($country) : null) ?? trim($phone)]);
        }

        if (is_string($this->input('tracking_url_pattern'))) {
            $this->merge(['tracking_url_pattern' => trim((string) $this->input('tracking_url_pattern'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone_country' => ['nullable', 'string', 'size:2'],
            'contact_phone' => ['nullable', 'string', 'max:32', (new PhoneRule)->countryField('contact_phone_country')],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'tracking_url_pattern' => ['nullable', 'string', 'max:500', 'regex:/^https?:\/\/[^\s\/$.?#][^\s]*$/i', static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! str_contains($value, ShippingCompany::TRACKING_PLACEHOLDER)) {
                    $fail(__('shipping::companies.tracking_placeholder_missing'));
                }
            }],
            'is_active' => ['required', 'boolean'],
            'logo' => ['nullable', 'array', 'max:1'],
            'logo.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'logo_keep' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['tracking_url_pattern.regex' => __('validation.url', ['attribute' => __('shipping::companies.fields.tracking_url')])];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('shipping::companies.fields.name'),
            'contact_email' => __('shipping::companies.fields.contact_email'),
            'contact_phone' => __('shipping::companies.fields.contact_phone'),
            'website' => __('shipping::companies.fields.website'),
            'tracking_url_pattern' => __('shipping::companies.fields.tracking_url'),
            'logo' => __('shipping::companies.fields.logo'),
            'logo.*' => __('shipping::companies.fields.logo'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim((string) $this->validated($key)) : null;
        $logo = $this->file('logo');
        $upload = is_array($logo) ? ($logo[0] ?? null) : $logo;

        return [
            'name' => trim((string) $this->validated('name')),
            'contact_email' => $text('contact_email'),
            'contact_phone' => $text('contact_phone'),
            'website' => $text('website'),
            'tracking_url_pattern' => $text('tracking_url_pattern'),
            'is_active' => $this->boolean('is_active'),
            'logo' => $upload instanceof UploadedFile ? $upload : null,
            'keep_logo' => (array) $this->input('logo_keep', []) !== [],
        ];
    }
}
