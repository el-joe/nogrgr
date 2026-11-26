<?php

namespace App\Modules\Ai\Http\Requests\Admin;

use App\Modules\Localization\Services\Translations\TranslationFileRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * ADM-092a: the scope of an AI translation of static keys (estimate modal and start):
 * the language, the file of the editor and "file" (that file) or "all" (every file).
 */
class StaticKeyTranslationRequest extends FormRequest
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
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/', Rule::notIn(['en'])],
            'file' => ['required', 'string', 'max:96'],
            'scope' => ['sometimes', Rule::in(['file', 'all'])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('file') && app(TranslationFileRegistry::class)->find((string) $this->input('file')) === null) {
                $validator->errors()->add('file', (string) __('ai::admin_translation.errors.unknown_file'));
            }
        }];
    }

    public function targetLocale(): string
    {
        return (string) $this->validated('locale');
    }

    public function fileId(): string
    {
        return (string) $this->validated('file');
    }

    /**
     * The file to translate, or null for every file.
     */
    public function scopedFile(): ?string
    {
        return ($this->validated('scope') ?? 'file') === 'all' ? null : $this->fileId();
    }
}
