<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What a maintenance window takes offline (ADM-113). Admin, vendor, affiliate and owner
 * panels always keep working.
 */
enum MaintenanceScope: string
{
    use HasEnumHelpers;

    /** Central website + every storefront + Store API. */
    case Platform = 'platform';

    /** Every storefront + Store API; the central website stays online. */
    case Storefronts = 'storefronts';

    /** One store's storefront + Store API (tenant_id). */
    case Store = 'store';

    public function tone(): string
    {
        return match ($this) {
            self::Platform => 'danger',
            self::Storefronts => 'warning',
            self::Store => 'info',
        };
    }

    public function coversCentral(): bool
    {
        return $this === self::Platform;
    }
}
