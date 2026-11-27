<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Storefront\DTOs\Themes\ThemeMetaData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * ADM-086a theme edit: name (required locales) and description per locale, preview image
 * tile (upload a new one, or remove the uploaded one).
 */
class ThemeMetaRequest extends FormRequest
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
            ...app(CatalogLocales::class)->rules('name', ['string', 'max:80']),
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:500'],
            'preview' => ['nullable', 'array', 'max:1'],
            'preview.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
            'preview_keep' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'preview' => __('content::admin_themes.fields.preview'),
            'preview.*' => __('content::admin_themes.fields.preview'),
        ];
    }

    public function toData(): ThemeMetaData
    {
        $locales = app(CatalogLocales::class);
        $file = $this->file('preview');
        $file = is_array($file) ? ($file[0] ?? null) : $file;

        return new ThemeMetaData(
            name: $locales->clean((array) $this->validated('name')),
            description: $locales->clean((array) $this->validated('description', [])),
            preview: $file instanceof UploadedFile ? $file : null,
            removePreview: ! $file instanceof UploadedFile && (array) $this->input('preview_keep', []) === [],
        );
    }
}
