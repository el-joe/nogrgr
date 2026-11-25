<?php

namespace App\Modules\Commerce\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Active switch of a list row: {is_active: bool}.
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
