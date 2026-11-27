<?php

namespace App\Modules\Notifications\Http\Requests\Vendor;

use App\Modules\Notifications\DTOs\StoreEmailTemplateData;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Store e-mail template edit (VEN-080a): subject + HTML body per locale, English
 * required (fallback language of every e-mail); the service sanitises the body.
 */
class StoreEmailTemplateRequest extends FormRequest
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
            'subject' => ['required', 'array'],
            'subject.en' => ['required', 'string', 'max:191'],
            'subject.ar' => ['nullable', 'string', 'max:191', 'required_with:body.ar'],
            'body' => ['required', 'array'],
            'body.en' => ['required', 'string', 'max:65000', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim(strip_tags($value)) === '') {
                    $fail(__('validation.required', ['attribute' => __('notifications::templates.fields.body')]));
                }
            }],
            'body.ar' => ['nullable', 'string', 'max:65000'],
            'status' => ['nullable', Rule::enum(EmailTemplateStatus::class)],
        ];
    }

    /**
     * Only the event's documented variables may be used (decision #118).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $event = EmailTemplateEvent::tryFrom((string) $this->route('event'));

            if ($event === null) {
                return;
            }

            $known = array_keys($event->variables());

            foreach (['subject', 'body'] as $field) {
                foreach (EmailTemplate::LOCALES as $locale) {
                    preg_match_all(EmailTemplateRenderer::PLACEHOLDER, (string) $this->input($field.'.'.$locale), $matches);
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
        $attributes = [];

        foreach (EmailTemplate::LOCALES as $locale) {
            $attributes['subject.'.$locale] = __('notifications::templates.fields.subject').' ('.strtoupper($locale).')';
            $attributes['body.'.$locale] = __('notifications::templates.fields.body').' ('.strtoupper($locale).')';
        }

        return $attributes;
    }

    public function toData(): StoreEmailTemplateData
    {
        $pick = static fn (mixed $values): array => array_filter(
            array_intersect_key(array_map(static fn (mixed $value): string => (string) $value, (array) $values), array_flip(EmailTemplate::LOCALES)),
            static fn (string $value): bool => $value !== '',
        );

        return new StoreEmailTemplateData(
            subject: $pick($this->validated('subject')),
            body: $pick($this->validated('body')),
            status: EmailTemplateEvent::tryFrom((string) $this->route('event'))?->isMandatory() === true ? EmailTemplateStatus::Active : (EmailTemplateStatus::tryFrom((string) $this->input('status')) ?? EmailTemplateStatus::Active),
        );
    }
}
