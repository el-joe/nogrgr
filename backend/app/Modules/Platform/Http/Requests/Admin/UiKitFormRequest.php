<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation demo of the component library form: shows the 422 contract on every
 * field type (inputs, Select2, phone, dates, locale tabs, uploads).
 */
class UiKitFormRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'category' => ['required', 'string'],
            'countries' => ['required', 'array', 'min:1'],
            'phone' => ['required', 'string', 'min:6'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:500'],
            'terms' => ['accepted'],
        ];

        foreach (array_keys(Locale::enabled()) as $locale) {
            $rules['title.'.$locale] = ['required', 'string', 'max:120'];
        }

        return $rules;
    }
}
