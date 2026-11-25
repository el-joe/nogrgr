<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve (note optional) or reject (note required) a store change request.
 */
class TenantChangeDecisionRequest extends FormRequest
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
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => (string) __('catalog::requests.tenant_changes.note')];
    }

    public function approves(): bool
    {
        return $this->validated('decision') === 'approve';
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
