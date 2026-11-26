<?php

namespace App\Modules\Ai\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Search by image (storefront modal + mobile store API): a JPG / PNG / WEBP photo up to
 * 10 MB, or an https image URL.
 */
class ImageSearchRequest extends FormRequest
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
            'image' => ['required_without:url', 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'url' => ['required_without:image', 'nullable', 'string', 'url:https', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required_without' => __st('search.image.errors.missing'),
            'url.required_without' => __st('search.image.errors.missing'),
            'image.mimetypes' => __st('search.image.errors.type'),
            'image.max' => __st('search.image.errors.size'),
            'image.file' => __st('search.image.errors.type'),
            'url.url' => __st('search.image.errors.url'),
            'url.max' => __st('search.image.errors.url'),
        ];
    }
}
