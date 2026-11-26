<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use App\Modules\Platform\Http\Requests\Owner\OwnerDomainHostRequest;

/**
 * Vendor › Settings › Domains › Add domain (VEN-082): the same host rules as the owner
 * portal (normalised, valid, not on the platform domain, not used by any store).
 */
class VendorDomainStoreRequest extends OwnerDomainHostRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['host' => $this->hostRules()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['host' => (string) __('platform::vendor_domains.fields.host')];
    }
}
