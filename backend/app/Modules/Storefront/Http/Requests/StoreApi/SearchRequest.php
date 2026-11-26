<?php

namespace App\Modules\Storefront\Http\Requests\StoreApi;

/**
 * Store API search: `q` is required (1–100 chars); filters / sort / page as the listing.
 */
class SearchRequest extends ProductIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'max:100']] + parent::rules();
    }
}
