<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Page builder › image of an "Image + text" section (PNG / JPG / WebP, max 4 MB).
 */
class PageBuilderImageRequest extends FormRequest
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
        return ['image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['image' => (string) __('content::page_builder.fields.image')];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('image');
        assert($file instanceof UploadedFile);

        return $file;
    }
}
