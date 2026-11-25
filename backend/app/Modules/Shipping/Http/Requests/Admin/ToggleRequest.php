<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Switch toggle (x-ui::switch url=…): {is_active: bool}.
 */
class ToggleRequest extends FormRequest
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
        return $this->boolean('is_active');
    }
}
