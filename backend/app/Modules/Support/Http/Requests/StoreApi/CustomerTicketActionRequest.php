<?php

namespace App\Modules\Support\Http\Requests\StoreApi;

/**
 * A signed-in customer action without input (show, close, options).
 */
class CustomerTicketActionRequest extends CustomerTicketRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
