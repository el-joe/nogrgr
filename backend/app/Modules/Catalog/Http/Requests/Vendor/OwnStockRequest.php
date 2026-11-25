<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Inventory stock edit (VEN-016b): product stock, or stock per variant id.
 */
class OwnStockRequest extends FormRequest
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
            'stock' => ['required_without:variants', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'variants' => ['nullable', 'array'],
            'variants.*' => ['integer', 'min:0', 'max:10000000'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function variantStock(): array
    {
        $result = [];

        foreach ((array) $this->validated('variants', []) as $id => $stock) {
            $result[(int) $id] = (int) $stock;
        }

        return $result;
    }
}
