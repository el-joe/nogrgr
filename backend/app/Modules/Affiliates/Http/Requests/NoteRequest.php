<?php

namespace App\Modules\Affiliates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional admin note (reject a conversion / payout request).
 */
class NoteRequest extends FormRequest
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
        return ['note' => ['nullable', 'string', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => (string) __('affiliates::admin.fields.note')];
    }

    public function note(): ?string
    {
        return $this->filled('note') ? trim((string) $this->validated('note')) : null;
    }
}
