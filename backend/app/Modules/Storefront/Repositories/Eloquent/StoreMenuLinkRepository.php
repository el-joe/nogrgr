<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Enums\MenuLinkPlacement;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Modules\Storefront\Repositories\Contracts\StoreMenuLinkRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<StoreMenuLink>
 */
class StoreMenuLinkRepository extends BaseRepository implements StoreMenuLinkRepositoryContract
{
    protected function model(): string
    {
        return StoreMenuLink::class;
    }

    public function forPlacement(MenuLinkPlacement $placement, bool $activeOnly = false): Collection
    {
        return $this->query()
            ->where('placement', $placement->value)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function nextSortOrder(MenuLinkPlacement $placement): int
    {
        return (int) $this->query()->where('placement', $placement->value)->max('sort_order') + 1;
    }

    public function reorder(MenuLinkPlacement $placement, array $ids): void
    {
        DB::transaction(function () use ($placement, $ids): void {
            foreach (array_values($ids) as $index => $id) {
                $this->query()->where('placement', $placement->value)->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }
}
