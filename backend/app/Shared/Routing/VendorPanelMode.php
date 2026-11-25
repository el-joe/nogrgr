<?php

namespace App\Shared\Routing;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How the vendor panel is being served.
 */
enum VendorPanelMode: string
{
    use HasEnumHelpers;

    /** `{store-domain}/admin/...`, login at `{store-domain}/login`. */
    case TenantHost = 'tenant_host';

    /** `{central}/tenant/{store}/...`, login at `{central}/tenant/login`. */
    case CentralHost = 'central_host';
}
