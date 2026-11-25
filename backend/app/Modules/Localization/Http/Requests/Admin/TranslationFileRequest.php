<?php

namespace App\Modules\Localization\Http\Requests\Admin;

use App\Modules\Localization\DTOs\TranslationFileData;
use App\Modules\Localization\Services\Translations\TranslationFileRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a panel translation file (ADM-092 "create file"): where (app lang or a module),
 * file name, and its first key with values.
 */
class TranslationFileRequest extends FormRequest
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
            'namespace' => ['nullable', 'string', Rule::in(array_keys(app(TranslationFileRegistry::class)->moduleNamespaces()))],
            'name' => ['required', 'string', 'max:48', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'key' => ['required', 'string', 'max:191', 'regex:'.TranslationValuesRequest::KEY_PATTERN],
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:'.TranslationValuesRequest::VALUE_MAX],
            'values.en' => ['required', 'string', 'max:'.TranslationValuesRequest::VALUE_MAX],
        ];
    }

    public function fileData(): TranslationFileData
    {
        /** @var array<string, string|null> $values */
        $values = (array) $this->validated('values');
        $namespace = $this->validated('namespace');

        return new TranslationFileData(is_string($namespace) && $namespace !== '' ? $namespace : null, (string) $this->validated('name'), (string) $this->validated('key'), $values);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'namespace' => __('localization::translations.fields.location'),
            'name' => __('localization::translations.fields.file_name'),
            'key' => __('localization::translations.fields.key'),
            'values.en' => __('localization::translations.fields.value_in', ['language' => 'English']),
        ];
    }
}
