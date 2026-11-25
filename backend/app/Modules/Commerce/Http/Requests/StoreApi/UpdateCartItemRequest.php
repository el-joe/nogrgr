<?php

namespace App\Modules\Commerce\Http\Requests\StoreApi;

use App\Modules\Commerce\Services\Cart\CartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Quantity 0 removes the line.
 */
class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QUANTITY],
        ];
    }
}
