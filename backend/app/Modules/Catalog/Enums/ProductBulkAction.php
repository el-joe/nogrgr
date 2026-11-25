<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Products list bulk actions (ADM-010, Figma Admin Panel 44:13636): activate publishes
 * the products, deactivate takes them back to draft, delete moves them to the trash.
 */
enum ProductBulkAction: string
{
    use HasEnumHelpers;

    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case Delete = 'delete';

    public function targetStatus(): ?ProductStatus
    {
        return match ($this) {
            self::Activate => ProductStatus::Published,
            self::Deactivate => ProductStatus::Draft,
            self::Delete => null,
        };
    }
}
