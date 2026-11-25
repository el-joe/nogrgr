<?php

namespace App\Modules\AfterSales\Http\Requests\StoreApi;

/**
 * Customer returns list (SF-ECO-031 / APP-017).
 */
class ReturnIndexRequest extends StoreApiCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['per_page' => ['nullable', 'integer', 'min:1', 'max:50']];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
