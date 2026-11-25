<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Events\PromotionsChanged;
use App\Modules\Commerce\Repositories\Contracts\HiddenPromotionRepositoryContract;

/**
 * A store hides / shows a central coupon or flash sale (read-only central tab of
 * VEN-040 / 041). Only the store's own tenant row changes; the central row is untouched.
 */
class CentralPromotionVisibilityService
{
    public function __construct(private readonly HiddenPromotionRepositoryContract $hidden) {}

    public function setHidden(PromotionKind $kind, int $centralId, bool $hidden): void
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        $hidden ? $this->hidden->hide($kind, $centralId) : $this->hidden->unhide($kind, $centralId);

        PromotionsChanged::dispatch($kind, DiscountSource::Central, $centralId, $tenantId);
    }
}
