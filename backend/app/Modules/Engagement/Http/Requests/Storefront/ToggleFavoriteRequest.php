<?php

namespace App\Modules\Engagement\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Heart on a product card / page (SF-ECO-033): the store product id.
 */
class ToggleFavoriteRequest extends FormRequest
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
        return ['store_product_id' => ['required', 'integer', 'min:1']];
    }

    public function productId(): int
    {
        return (int) $this->validated('store_product_id');
    }
}
