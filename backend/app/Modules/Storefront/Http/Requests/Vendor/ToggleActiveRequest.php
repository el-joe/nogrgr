<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * On / off switch of an appearance row (header / footer link, social link): {is_active}.
 */
class ToggleActiveRequest extends FormRequest
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
