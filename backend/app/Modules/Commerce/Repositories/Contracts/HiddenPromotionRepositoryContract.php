<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Models\HiddenCentralPromotion;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Central promotions a store hides (tenant DB).
 *
 * @extends RepositoryContract<HiddenCentralPromotion>
 */
interface HiddenPromotionRepositoryContract extends RepositoryContract
{
    /**
     * @return list<int> hidden central ids of the kind
     */
    public function hiddenIds(PromotionKind $kind): array;

    public function hide(PromotionKind $kind, int $centralId): void;

    public function unhide(PromotionKind $kind, int $centralId): void;
}
