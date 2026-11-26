<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Home variant of a scope's current theme family (VEN-030b / VEN-031): {variant: 1…n}.
 * The variant must exist in the catalogue for that family (checked by ThemePageService).
 */
class ThemeVariantRequest extends FormRequest
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
        return ['variant' => ['required', 'integer', 'min:1', 'max:99']];
    }

    public function variant(): int
    {
        return (int) $this->validated('variant');
    }
}
