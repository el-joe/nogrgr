<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Store logo (setup checklist step "Upload your logo"). Raster only: an SVG served
 * from the store's own host could carry script.
 */
class UploadStoreLogoRequest extends FormRequest
{
    public const MAX_KB = 2048;

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
            'logo' => [
                'required',
                File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(self::MAX_KB)->dimensions(Rule::dimensions()->minWidth(64)->minHeight(64)->maxWidth(4000)->maxHeight(4000)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['logo' => __('platform::onboarding.logo.field')];
    }
}
