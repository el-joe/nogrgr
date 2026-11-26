<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * On / off switch of a store banner row (VEN-035): {is_active: 1|0}.
 */
class ToggleStoreBannerRequest extends FormRequest
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
        return ['is_active' => ['required', 'boolean']];
    }

    public function value(): bool
    {
        return (bool) $this->validated('is_active');
    }
}
