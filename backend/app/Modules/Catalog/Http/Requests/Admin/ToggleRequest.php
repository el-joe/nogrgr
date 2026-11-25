<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Switch PATCH from a list row: {<field>: 0|1}. The field is named by the route
 * (`is_featured` for categories, `is_active` for labels).
 */
class ToggleRequest extends FormRequest
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
        return [$this->field() => ['required', 'boolean']];
    }

    public function value(): bool
    {
        return $this->boolean($this->field());
    }

    private function field(): string
    {
        return $this->routeIs('admin.catalog.categories.featured') ? 'is_featured' : 'is_active';
    }
}
