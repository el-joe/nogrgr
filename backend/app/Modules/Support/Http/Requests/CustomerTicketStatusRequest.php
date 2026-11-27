<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Support\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store staff status change of a customer ticket (route segment): resolved, closed or
 * open (reopen).
 */
class CustomerTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['status' => $this->route('status')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in([TicketStatus::Resolved->value, TicketStatus::Closed->value, TicketStatus::Open->value])]];
    }

    public function status(): TicketStatus
    {
        return TicketStatus::from((string) $this->validated('status'));
    }
}
