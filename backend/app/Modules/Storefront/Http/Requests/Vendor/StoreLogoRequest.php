<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Appearance › Logo: replace the store logo (PNG / JPG / WebP / SVG, max 2 MB).
 */
class StoreLogoRequest extends FormRequest
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
            'logo' => ['required', 'array', 'max:1'],
            'logo.*' => ['file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['logo' => __('content::store_appearance.logo.file'), 'logo.*' => __('content::store_appearance.logo.file')];
    }

    public function logoFile(): UploadedFile
    {
        $files = $this->file('logo');
        $file = is_array($files) ? ($files[0] ?? null) : $files;

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
