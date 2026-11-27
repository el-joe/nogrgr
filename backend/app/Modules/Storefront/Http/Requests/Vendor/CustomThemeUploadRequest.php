<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Storefront\Support\CustomThemes\CustomThemeLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * VEN-036 upload: one .zip theme package (size cap here; the package itself is checked by
 * ThemePackageValidator and its findings shown in the validation report).
 */
class CustomThemeUploadRequest extends FormRequest
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
        return ['package' => ['required', 'file', 'extensions:zip', 'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream', 'max:'.(CustomThemeLimits::ZIP_MAX_BYTES / 1024)]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['package' => __('content::custom_themes.upload.field')];
    }

    public function package(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('package');

        return $file;
    }
}
