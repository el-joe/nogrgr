<?php

namespace App\Modules\Ai\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * VEN-012b "Translate with AI": preview = {locales: [code…]}; apply =
 * {translations: {locale: {item key: {field: text}}}} (item key "product" or "variant:<id>").
 * The service checks the locales against the store languages and ignores fields AI may
 * not write.
 */
class ProductAiTranslationRequest extends FormRequest
{
    public const MAX_TEXT = 20000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isApply()) {
            return [
                'translations' => ['required', 'array', 'max:20'],
                'translations.*' => ['array', 'max:200'],
                'translations.*.*' => ['array', 'max:10'],
                'translations.*.*.*' => ['nullable', 'string', 'max:'.self::MAX_TEXT],
            ];
        }

        return [
            'locales' => ['required', 'array', 'min:1', 'max:20'],
            'locales.*' => ['required', 'string', 'distinct', 'regex:/^[a-z]{2,3}([_-][A-Za-z]{2,4})?$/'],
        ];
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(array_map('strval', (array) $this->validated('locales', [])));
    }

    /**
     * @return array<string, array<string, array<string, string|null>>>
     */
    public function translations(): array
    {
        /** @var array<string, array<string, array<string, string|null>>> $translations */
        $translations = (array) $this->validated('translations', []);

        return $translations;
    }

    private function isApply(): bool
    {
        return str_ends_with((string) $this->route()?->getName(), '.apply');
    }
}
