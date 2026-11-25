<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Switch PATCH from a product card: {is_active|is_featured: 0|1} (field named by the route).
 */
class StoreToggleRequest extends FormRequest
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
        return str_ends_with((string) $this->route()?->getName(), '.featured') ? 'is_featured' : 'is_active';
    }
}
