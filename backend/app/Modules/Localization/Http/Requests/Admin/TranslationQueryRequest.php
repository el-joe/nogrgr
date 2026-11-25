<?php

namespace App\Modules\Localization\Http\Requests\Admin;

use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Modules\Localization\Services\Translations\TranslationFileRegistry;
use App\Modules\Localization\Support\TranslationFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Query of the translation editor pages and endpoints (ADM-092 / 093): file, language,
 * tab and (for one entry) the key. Unknown files / languages fall back to the defaults.
 */
class TranslationQueryRequest extends FormRequest
{
    public const TABS = ['all', 'missing', 'missing_ar', 'locked'];

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
            'file' => ['sometimes', 'nullable', 'string', 'max:96'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:12'],
            'tab' => ['sometimes', 'nullable', Rule::in(self::TABS)],
            'key' => [$this->keyRequired() ? 'required' : 'sometimes', 'string', 'max:191'],
        ];
    }

    public function translationFile(): TranslationFile
    {
        $id = (string) $this->query('file', $this->input('file', StaticTextCatalog::FILE));

        return app(TranslationFileRegistry::class)->find($id) ?? TranslationFile::fromId(StaticTextCatalog::FILE) ?? throw new LogicException('Storefront file missing.');
    }

    public function editorLocale(): string
    {
        $locale = $this->query('locale', $this->input('locale'));

        return app(TranslationEditorService::class)->resolveLocale(is_string($locale) ? $locale : null);
    }

    public function editorTab(): string
    {
        $tab = $this->query('tab', $this->input('tab'));

        return in_array($tab, self::TABS, true) ? (string) $tab : 'all';
    }

    public function translationKey(): string
    {
        return (string) $this->validated('key');
    }

    protected function keyRequired(): bool
    {
        return false;
    }
}
