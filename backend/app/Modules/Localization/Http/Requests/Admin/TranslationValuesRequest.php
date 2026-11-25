<?php

namespace App\Modules\Localization\Http\Requests\Admin;

use App\Modules\Localization\DTOs\TranslationValuesData;
use App\Modules\Localization\Enums\TranslationFileKind;
use App\Modules\Localization\Services\Translations\TranslationFileRegistry;
use App\Modules\Localization\Support\TranslationFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Save the values of one key (edit) or add a key to a file (ADM-092). Keys of PHP and
 * storefront files are dotted paths (storefront: "<group>.<key>"), JSON keys free text.
 */
class TranslationValuesRequest extends FormRequest
{
    public const KEY_PATTERN = '/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/';

    public const VALUE_MAX = 5000;

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
            'file' => ['required', 'string', Rule::in(array_keys(app(TranslationFileRegistry::class)->files()))],
            'key' => ['required', 'string', 'max:191'],
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:'.self::VALUE_MAX],
            'values.en' => [$this->isMethod('POST') ? 'required' : 'nullable', 'string', 'max:'.self::VALUE_MAX],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = TranslationFile::fromId((string) $this->input('file'));
            $key = (string) $this->input('key');

            if ($file !== null && $file->kind !== TranslationFileKind::Json && preg_match(self::KEY_PATTERN, $key) !== 1) {
                $validator->errors()->add('key', __('localization::translations.validation.key_format'));
            }

            if ($file !== null && $file->isStorefront() && ! str_contains($key, '.')) {
                $validator->errors()->add('key', __('localization::translations.validation.storefront_key'));
            }

            if (preg_match('/[\x00-\x1F]/', $key) === 1) {
                $validator->errors()->add('key', __('localization::translations.validation.key_format'));
            }
        }];
    }

    public function valuesData(): TranslationValuesData
    {
        /** @var array<string, string|null> $values */
        $values = (array) $this->validated('values');

        return new TranslationValuesData((string) $this->validated('file'), (string) $this->validated('key'), $values);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => __('localization::translations.fields.key'),
            'values.en' => __('localization::translations.fields.value_in', ['language' => 'English']),
        ];
    }
}
