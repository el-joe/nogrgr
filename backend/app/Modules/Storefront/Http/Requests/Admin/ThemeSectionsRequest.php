<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ADM-087 home sections: every section key in the new order + the keys switched on.
 */
class ThemeSectionsRequest extends FormRequest
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
            'order' => ['required', 'array', 'max:60'],
            'order.*' => ['string', 'max:64', 'distinct'],
            'enabled' => ['nullable', 'array', 'max:60'],
            'enabled.*' => ['string', 'max:64'],
        ];
    }

    /**
     * @return list<string>
     */
    public function order(): array
    {
        return array_values(array_map('strval', (array) $this->validated('order')));
    }

    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_values(array_map('strval', (array) $this->validated('enabled', [])));
    }
}
