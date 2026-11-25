<?php

namespace App\Modules\Notifications\Http\Requests\Admin;

use App\Modules\Notifications\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Send test" of a template: recipient + locale.
 */
class SendTestEmailTemplateRequest extends FormRequest
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
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'locale' => ['required', 'string', Rule::in(EmailTemplate::LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('notifications::templates.fields.test_email'),
            'locale' => __('notifications::templates.fields.locale'),
        ];
    }
}
