<?php

namespace App\Modules\Storefront\Http\Requests\Vendor;

use App\Modules\Storefront\Enums\MenuLinkPlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Drag & drop order of the header (or footer) links: {placement, ids: [...]}.
 */
class ReorderMenuLinksRequest extends FormRequest
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
            'placement' => ['required', Rule::enum(MenuLinkPlacement::class)],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    public function placement(): MenuLinkPlacement
    {
        return MenuLinkPlacement::from((string) $this->validated('placement'));
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->validated('ids')));
    }
}
