<?php

namespace App\Modules\Ai\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ADM-091b: start the AI translation of the central catalogue into one active language.
 */
class CatalogTranslationRequest extends FormRequest
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
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/'],
        ];
    }

    public function targetLocale(): string
    {
        return (string) $this->validated('locale');
    }
}
