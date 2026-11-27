<?php

namespace App\Modules\Notifications\Http\Requests\Admin;

use App\Modules\Notifications\DTOs\EmailTemplateData;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Template create (event required, unique) / edit (event fixed). English is
 * required; Arabic may be completed later (list shows "Missing AR"). Bodies are
 * sanitised before validation; unknown `{{ variables }}` are rejected.
 */
class EmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $body = [];

        foreach (EmailTemplate::LOCALES as $locale) {
            $value = $this->input('body.'.$locale);
            $body[$locale] = is_string($value) ? EmailHtmlSanitizer::clean($value) : '';
        }

        $this->merge(['body' => $body]);
    }

    public function routeTemplate(): EmailTemplate
    {
        $template = $this->template();
        abort_if($template === null, 404);

        return $template;
    }

    public function template(): ?EmailTemplate
    {
        $template = $this->route('template');

        return $template instanceof EmailTemplate ? $template : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['prohibited'],
            'status' => [$this->template()?->event->isMandatory() === true ? 'nullable' : 'required', Rule::enum(EmailTemplateStatus::class)],
            'subject' => ['required', 'array'],
            'subject.en' => ['required', 'string', 'max:191'],
            'subject.ar' => ['nullable', 'string', 'max:191', 'required_with:body.ar'],
            'body' => ['required', 'array'],
            'body.en' => ['required', 'string', 'max:65000'],
            'body.ar' => ['nullable', 'string', 'max:65000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $event = $this->template()?->event;

            if ($event === null) {
                return;
            }

            $known = array_keys($event->variables());

            foreach (['subject', 'body'] as $field) {
                foreach (EmailTemplate::LOCALES as $locale) {
                    preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', (string) $this->input($field.'.'.$locale), $matches);
                    $unknown = array_values(array_diff(array_unique($matches[1]), $known));

                    if ($unknown !== []) {
                        $validator->errors()->add($field.'.'.$locale, __('notifications::templates.unknown_variables', ['variables' => implode(', ', $unknown)]));
                    }
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event' => __('notifications::templates.fields.event'),
            'status' => __('notifications::templates.fields.status'),
            'subject.en' => __('notifications::templates.fields.subject_en'),
            'subject.ar' => __('notifications::templates.fields.subject_ar'),
            'body.en' => __('notifications::templates.fields.body_en'),
            'body.ar' => __('notifications::templates.fields.body_ar'),
        ];
    }

    public function payload(): EmailTemplateData
    {
        return new EmailTemplateData(
            event: $this->routeTemplate()->event,
            subject: array_filter(['en' => trim((string) $this->input('subject.en')), 'ar' => trim((string) $this->input('subject.ar'))], static fn (string $value): bool => $value !== ''),
            body: array_filter(['en' => (string) $this->input('body.en'), 'ar' => (string) $this->input('body.ar')], static fn (string $value): bool => trim(strip_tags($value)) !== ''),
            status: $this->routeTemplate()->event->isMandatory() ? EmailTemplateStatus::Active : EmailTemplateStatus::from((string) $this->input('status')),
        );
    }
}
