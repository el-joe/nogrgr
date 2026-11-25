<?php

namespace App\Modules\AfterSales\Http\Requests\StoreApi;

/**
 * Withdraw a return under review, with an optional note.
 */
class WithdrawReturnRequest extends StoreApiCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('after-sales::returns.fields.note')];
    }

    public function note(): ?string
    {
        $note = trim((string) $this->validated('note'));

        return $note === '' ? null : $note;
    }
}
