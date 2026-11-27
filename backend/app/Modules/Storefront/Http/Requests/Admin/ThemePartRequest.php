<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\DTOs\Themes\ThemePartData;
use App\Modules\Storefront\Enums\ThemePartType;
use App\Modules\Storefront\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * ADM-088a template part: theme, type, name, flags, preview image and the settings the
 * type declares (ThemePartType::fields): text = per locale, link = store path or https
 * URL, select = one of the options, toggle = boolean.
 */
class ThemePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = ThemePartType::tryFrom((string) $this->input('type'));
        $settings = (array) $this->input('settings', []);

        foreach ($type?->fields() ?? [] as $field) {
            if ($field['kind'] === 'toggle') {
                $settings[$field['name']] = filter_var($settings[$field['name']] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            if ($field['kind'] === 'link') {
                $link = $settings[$field['name']] ?? null;
                $settings[$field['name']] = is_string($link) && trim($link) !== '' ? trim($link) : null;
            }
        }

        $this->merge([
            'settings' => $settings,
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'theme_id' => ['required', 'integer', Rule::exists(Theme::class, 'id')],
            'type' => ['required', Rule::enum(ThemePartType::class)],
            'name' => ['required', 'string', 'max:120'],
            'settings' => ['array'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'preview' => ['nullable', 'array', 'max:1'],
            'preview.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
            'preview_keep' => ['nullable', 'array'],
        ];

        $type = ThemePartType::tryFrom((string) $this->input('type'));
        $locales = app(CatalogLocales::class);

        foreach ($type?->fields() ?? [] as $field) {
            $key = 'settings.'.$field['name'];
            $max = 'max:'.($field['max'] ?? 255);

            $rules += match ($field['kind']) {
                'text' => $locales->rules($key, ['string', $max], (bool) ($field['required'] ?? false)),
                'link' => [$key => ['nullable', 'string', $max, 'regex:#^(/[^\s]*|https?://[^\s]+)$#i']],
                'select' => [$key => ['required', Rule::in($field['options'] ?? [])]],
                'toggle' => [$key => ['boolean']],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['preview' => __('content::admin_themes.fields.preview'), 'preview.*' => __('content::admin_themes.fields.preview')];

        foreach (ThemePartType::tryFrom((string) $this->input('type'))?->fields() ?? [] as $field) {
            $label = (string) __('content::admin_themes.part_fields.'.$field['name']);
            $attributes['settings.'.$field['name']] = $label;

            foreach (app(CatalogLocales::class)->codes() as $code) {
                $attributes['settings.'.$field['name'].'.'.$code] = $label;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['settings.link.regex' => __('content::admin_themes.errors.link')];
    }

    public function toData(): ThemePartData
    {
        $type = ThemePartType::from((string) $this->validated('type'));
        $input = (array) $this->validated('settings', []);
        $locales = app(CatalogLocales::class);
        $settings = [];

        foreach ($type->fields() as $field) {
            $value = $input[$field['name']] ?? null;
            $settings[$field['name']] = match ($field['kind']) {
                'text' => $locales->clean(is_array($value) ? $value : []),
                'toggle' => (bool) $value,
                default => $value,
            };
        }

        $file = $this->file('preview');
        $file = is_array($file) ? ($file[0] ?? null) : $file;

        return new ThemePartData(
            themeId: (int) $this->validated('theme_id'),
            type: $type,
            name: trim((string) $this->validated('name')),
            settings: $settings,
            isActive: (bool) $this->validated('is_active'),
            isDefault: (bool) $this->validated('is_default'),
            preview: $file instanceof UploadedFile ? $file : null,
            removePreview: ! $file instanceof UploadedFile && (array) $this->input('preview_keep', []) === [],
        );
    }
}
