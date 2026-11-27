<?php

namespace App\Modules\Support\Http\Requests\StoreApi;

/**
 * The customer's tickets list: status group (active / closed), page, per_page 1–50.
 */
class CustomerTicketIndexRequest extends CustomerTicketRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:active,closed'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function group(): ?string
    {
        $status = $this->validated('status');

        return is_string($status) ? $status : null;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
