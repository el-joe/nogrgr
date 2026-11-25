<?php

namespace App\Modules\Localization\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH {is_active: 0|1} sent by the list switches (<x-ui::switch url>).
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
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function active(): bool
    {
        return $this->boolean('is_active');
    }
}
