<?php

namespace App\Modules\Platform\Http\Requests\Owner;

use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\DTOs\OwnerDomainData;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Validation\Rule;

/**
 * Add domain (OWN-004a): the store must be one of the signed-in owner's stores.
 */
class OwnerDomainStoreRequest extends OwnerDomainHostRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = $this->user('owner');

        return [
            'tenant_id' => ['required', 'integer', Rule::exists(Tenant::class, 'id')->where('owner_id', $owner instanceof Owner ? $owner->id : 0)],
            'host' => $this->hostRules(),
        ];
    }

    public function toData(): OwnerDomainData
    {
        return new OwnerDomainData((int) $this->validated('tenant_id'), $this->host());
    }
}
