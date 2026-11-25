<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Create vendor › live sub-domain availability.
 */
class VendorSlugCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('vendors.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::lower(trim($this->input('slug')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:63'],
        ];
    }

    public function slug(): string
    {
        return (string) $this->validated('slug');
    }
}
