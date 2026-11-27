<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Live preview of unsaved editor content (Admin / Vendor › Email templates): one locale's
 * subject + body; the body is sanitised by the service before rendering.
 */
class RenderEmailTemplateRequest extends FormRequest
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
            'locale' => ['required', 'string', Rule::in(EmailTemplate::LOCALES)],
            'subject' => ['nullable', 'array'],
            'subject.*' => ['nullable', 'string', 'max:191'],
            'body' => ['nullable', 'array'],
            'body.*' => ['nullable', 'string', 'max:65000'],
        ];
    }

    public function locale(): string
    {
        return (string) $this->validated('locale');
    }

    public function subjectText(): string
    {
        return (string) $this->input('subject.'.$this->locale(), '');
    }

    public function bodyHtml(): string
    {
        return (string) $this->input('body.'.$this->locale(), '');
    }
}
