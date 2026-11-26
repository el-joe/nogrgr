<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Show / hide platform banners on the store (VEN-035): one central banner's switch or the
 * "Show platform banners" switch — {visible: 1|0}.
 */
class BannerVisibilityRequest extends FormRequest
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
        return ['visible' => ['required', 'boolean']];
    }

    public function visible(): bool
    {
        return (bool) $this->validated('visible');
    }
}
