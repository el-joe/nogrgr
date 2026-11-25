<?php

namespace App\Modules\Platform\Http\Requests\Owner;

use App\Modules\Platform\Models\DomainRequest;

/**
 * Edit domain (OWN-004b): correct the host of a request that is not connected yet.
 */
class OwnerDomainUpdateRequest extends OwnerDomainHostRequest
{
    protected function exceptId(): ?int
    {
        $domain = $this->route('domain');

        return $domain instanceof DomainRequest ? $domain->id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['host' => $this->hostRules()];
    }
}
