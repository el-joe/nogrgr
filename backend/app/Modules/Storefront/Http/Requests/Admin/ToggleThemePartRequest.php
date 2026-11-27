<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * On / off switch of a template part row (ADM-088).
 */
class ToggleThemePartRequest extends FormRequest
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
