<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sign out this install, or every install (`everywhere`).
 */
class LogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['everywhere' => ['sometimes', 'boolean']];
    }
}
