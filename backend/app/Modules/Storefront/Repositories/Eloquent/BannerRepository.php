<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Repositories\Contracts\BannerRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Banner>
 */
class BannerRepository extends BaseRepository implements BannerRepositoryContract
{
    protected function model(): string
    {
        return Banner::class;
    }

    public function syncTargets(Banner $banner, array $countryIds, array $tenantIds): void
    {
        $banner->countries()->sync($countryIds);
        $banner->tenants()->sync($banner->store_scope === PromotionScope::SelectedStores ? $tenantIds : []);
    }

    public function applicableToTenant(int $tenantId, ?BannerPlacement $placement = null, bool $liveOnly = false): Collection
    {
        $now = now();

        return $this->query()
            ->with('countries:id')
            ->when($placement !== null, fn (Builder $query) => $query->where('placement', $placement?->value))
            ->when($liveOnly, fn (Builder $query) => $query
                ->where('is_active', true)
                ->where(fn (Builder $inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>=', $now)))
            ->where(fn (Builder $query) => $query
                ->where('store_scope', PromotionScope::AllStores->value)
                ->orWhereHas('tenants', fn (Builder $tenants) => $tenants->whereKey($tenantId)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function nextSortOrder(BannerPlacement $placement): int
    {
        return (int) $this->query()->where('placement', $placement->value)->max('sort_order') + 1;
    }

    public function reorder(array $ids): void
    {
        DB::connection((new Banner)->getConnectionName())->transaction(function () use ($ids): void {
            // The listed banners swap their existing sort slots, so banners not shown in
            // the list (other countries / placement) keep their relative position.
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $slots = $this->query()->whereKey($ids)->orderBy('sort_order')->orderBy('id')->pluck('sort_order')->map(static fn ($slot): int => (int) $slot)->all();

            if (count($slots) !== count($ids)) {
                return;
            }

            for ($index = 1; $index < count($slots); $index++) {
                $slots[$index] = max($slots[$index], $slots[$index - 1] + 1);
            }

            foreach ($ids as $position => $id) {
                $this->query()->whereKey($id)->update(['sort_order' => $slots[$position]]);
            }
        });
    }

    public function countsByCountry(): array
    {
        $counts = DB::connection((new Banner)->getConnectionName())->table('banner_country')
            ->selectRaw('country_id, COUNT(*) as aggregate')
            ->groupBy('country_id')
            ->pluck('aggregate', 'country_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        $counts[0] = $this->query()->whereDoesntHave('countries')->count();

        return $counts;
    }
}
