<?php

namespace App\Modules\Ai\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-092a one-key "Suggest" of the translations editor edit modal.
 */
class StaticKeySuggestRequest extends FormRequest
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
            'file' => ['required', 'string', 'max:96'],
            'key' => ['required', 'string', 'max:191'],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/', Rule::notIn(['en'])],
        ];
    }
}
