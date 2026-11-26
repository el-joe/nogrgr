<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Identity\Models\Owner;
use Illuminate\Validation\Rule;

/**
 * New ticket from the owner portal (OWN-009): the store it is about (one of the owner's
 * stores) or none for an account question.
 */
class OwnerOpenTicketRequest extends OpenTicketRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Owner $owner */
        $owner = $this->user('owner');

        return [
            ...parent::rules(),
            'tenant_id' => ['nullable', 'integer', Rule::in($owner->tenants()->pluck('id')->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...parent::attributes(), 'tenant_id' => (string) __('support::tickets.fields.store')];
    }

    public function tenantId(): ?int
    {
        $id = $this->validated('tenant_id');

        return $id !== null && $id !== '' ? (int) $id : null;
    }
}
