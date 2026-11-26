<?php

namespace App\Modules\Platform\DTOs\Dashboard;

use App\Modules\Catalog\Models\Product;
use App\Shared\DTOs\BaseDTO;

/**
 * The permission-aware widgets of the vendor dashboard (VEN-009a / c / d / f). A widget
 * is absent (null) when the staff member lacks its permission; a widget whose data
 * failed to load is listed in $failed and renders its error state.
 */
final class VendorDashboardWidgetsData extends BaseDTO
{
    /**
     * @param  list<array{product: Product, storeProductId: int|null}>|null  $opportunities
     * @param  list<array<string, mixed>>|null  $offers  hidden central promotions the store can join
     * @param  list<Product>|null  $newIn
     * @param  list<string>  $failed  widget keys: owed | opportunities | new_in | storefront
     * @param  array<string, bool>  $can  action permissions used by the widgets
     */
    public function __construct(
        public readonly ?OwedToPlatformData $owed,
        public readonly ?array $opportunities,
        public readonly ?array $offers,
        public readonly ?array $newIn,
        public readonly ?StorefrontPreviewData $storefront,
        public readonly array $failed = [],
        public readonly array $can = [],
    ) {}

    public function failed(string $widget): bool
    {
        return in_array($widget, $this->failed, true);
    }

    public function can(string $action): bool
    {
        return $this->can[$action] ?? false;
    }
}
