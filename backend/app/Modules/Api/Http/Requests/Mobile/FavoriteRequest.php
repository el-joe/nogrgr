<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a product of the store to the wishlist.
 */
class FavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['store_product_id' => ['required', 'integer', 'min:1']];
    }
}
