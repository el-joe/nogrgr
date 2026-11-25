<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\DTOs\FaqData;
use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Http\Requests\Admin\Concerns\TranslatedContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FAQ create / edit (ADM-083a).
 */
class FaqRequest extends FormRequest
{
    use TranslatedContent;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslated(['answer'], 'question');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->translatedRules(['question' => [true, ['max:300']], 'answer' => [true, ['max:10000']]]),
            'group' => ['required', Rule::enum(FaqGroup::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'question.en' => __('content::admin.faqs.fields.question'),
            'answer.en' => __('content::admin.faqs.fields.answer'),
            'group' => __('content::admin.faqs.fields.group'),
        ];
    }

    public function toData(): FaqData
    {
        return new FaqData(
            group: FaqGroup::from((string) $this->validated('group')),
            question: $this->translations('question'),
            answer: $this->translations('answer'),
            isActive: $this->boolean('is_active'),
        );
    }
}
