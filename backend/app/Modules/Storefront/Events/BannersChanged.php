<?php

namespace App\Modules\Storefront\Events;

use App\Modules\Storefront\Enums\ContentSource;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A banner was created, changed, sorted, hidden or deleted (central: every targeted store;
 * vendor: only $tenantId). The storefront cache listens.
 */
final class BannersChanged
{
    use Dispatchable;

    public function __construct(
        public readonly ContentSource $source,
        public readonly ?int $bannerId = null,
        public readonly ?int $tenantId = null,
    ) {}
}
