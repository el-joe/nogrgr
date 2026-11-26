<?php

namespace App\Modules\Engagement\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Footer newsletter signup (SF-ECO-035): email; `website` honeypot must stay empty.
 */
class NewsletterSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:filter', 'max:160'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __st('support.fields.email')];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
