<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Inline switch of a store page row (VEN-033): {is_active: 1|0} (published) or
 * {show_in_footer: 1|0}.
 */
class TogglePageFlagRequest extends FormRequest
{
    public const FLAGS = ['is_active', 'show_in_footer'];

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
            'is_active' => ['required_without:show_in_footer', 'boolean'],
            'show_in_footer' => ['required_without:is_active', 'boolean'],
        ];
    }

    public function flag(): string
    {
        return $this->has('is_active') ? 'is_active' : 'show_in_footer';
    }

    public function value(): bool
    {
        return (bool) $this->validated($this->flag());
    }
}
