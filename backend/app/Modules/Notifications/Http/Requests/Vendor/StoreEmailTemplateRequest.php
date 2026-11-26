<?php

namespace App\Modules\Notifications\Http\Requests\Vendor;

use App\Modules\Notifications\DTOs\StoreEmailTemplateData;
use App\Modules\Notifications\Models\EmailTemplate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
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
        );
    }
}
