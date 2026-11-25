<?php

namespace App\Modules\Content\Http\Requests\Central;

use App\Modules\Content\Http\Requests\Concerns\DetectsSpam;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Central contact form (WEB-008). Throttled per IP by the route; honeypot via DetectsSpam.
 */
class ContactRequest extends FormRequest
{
    use DetectsSpam;
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'phone_country' => ['nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255'],
            'form_started_at' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('content::central.contact.fields.name'),
            'email' => __('content::central.contact.fields.email'),
            'phone' => __('content::central.contact.fields.phone'),
            'subject' => __('content::central.contact.fields.subject'),
            'message' => __('content::central.contact.fields.message'),
        ];
    }
}
