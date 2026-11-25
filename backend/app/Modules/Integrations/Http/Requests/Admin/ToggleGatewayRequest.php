<?php

namespace App\Modules\Integrations\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Active switch of a gateway row.
 */
class ToggleGatewayRequest extends FormRequest
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

    public function active(): bool
    {
        return $this->boolean('is_active');
    }
}
