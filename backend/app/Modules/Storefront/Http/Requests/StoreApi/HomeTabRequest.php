<?php

namespace App\Modules\Storefront\Http\Requests\StoreApi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Page of a home "explore your interests" tab.
 */
class HomeTabRequest extends FormRequest
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
        return ['page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
