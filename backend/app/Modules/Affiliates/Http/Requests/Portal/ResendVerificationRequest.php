<?php

namespace App\Modules\Affiliates\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AFF-006 "Send a new link": the sign-up e-mail.
 */
class ResendVerificationRequest extends FormRequest
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
        return ['email' => ['required', 'string', 'email:rfc', 'max:191']];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
