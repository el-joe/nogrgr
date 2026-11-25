<?php

namespace App\Modules\Localization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CitySuggestionsRequest extends FormRequest
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
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
