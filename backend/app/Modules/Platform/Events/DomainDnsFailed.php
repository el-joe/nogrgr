<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\DomainRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A DNS check found the records of a custom domain missing after they had been found
 * (or while the domain is connected). Listener: NotifyOfDomainDnsFailed (owner mail
 * `vendor.domain_dns_failed` + owner inbox, admins with vendors.domains.view inbox).
 */
final class DomainDnsFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly DomainRequest $request) {}
}
