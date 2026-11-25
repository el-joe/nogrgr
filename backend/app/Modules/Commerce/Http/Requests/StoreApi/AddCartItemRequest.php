<?php

namespace App\Modules\Commerce\Http\Requests\StoreApi;

use App\Modules\Commerce\Services\Cart\CartService;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
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
            'store_product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ];
    }
}
