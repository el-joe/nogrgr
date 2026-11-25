<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Models\HiddenCentralPromotion;
use App\Modules\Commerce\Repositories\Contracts\HiddenPromotionRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<HiddenCentralPromotion>
 */
class HiddenPromotionRepository extends BaseRepository implements HiddenPromotionRepositoryContract
{
    protected function model(): string
    {
        return HiddenCentralPromotion::class;
    }

    public function hiddenIds(PromotionKind $kind): array
    {
        return $this->query()->where('kind', $kind->value)->pluck('central_id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function hide(PromotionKind $kind, int $centralId): void
    {
        $this->query()->firstOrCreate(['kind' => $kind->value, 'central_id' => $centralId]);
    }

    public function unhide(PromotionKind $kind, int $centralId): void
    {
        $this->query()->where('kind', $kind->value)->where('central_id', $centralId)->delete();
    }
}
