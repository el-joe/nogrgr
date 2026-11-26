<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Support\Enums\HelpTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Help center index (VEN-092): ?q= search and ?topic= filter.
 */
class HelpSearchRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:120'],
            'topic' => ['nullable', 'string', Rule::enum(HelpTopic::class)],
        ];
    }

    public function term(): string
    {
        return trim((string) ($this->validated('q') ?? ''));
    }

    public function topic(): ?HelpTopic
    {
        return HelpTopic::tryFrom((string) ($this->validated('topic') ?? ''));
    }
}
