<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Models\StoreBannerCountry;
use App\Modules\Storefront\Repositories\Contracts\StoreBannerRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<StoreBanner>
 */
class StoreBannerRepository extends BaseRepository implements StoreBannerRepositoryContract
{
    protected function model(): string
    {
        return StoreBanner::class;
    }

    public function listing(?BannerPlacement $placement = null, bool $liveOnly = false): Collection
    {
        $now = now();

        return $this->query()
            ->with('countryRows')
            ->when($placement !== null, fn (Builder $query) => $query->where('placement', $placement?->value))
            ->when($liveOnly, fn (Builder $query) => $query
                ->where('is_active', true)
                ->where(fn (Builder $inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>=', $now)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function syncCountries(StoreBanner $banner, array $countryIds): void
    {
        StoreBannerCountry::query()->where('store_banner_id', $banner->id)->delete();

        foreach (array_values(array_unique($countryIds)) as $countryId) {
            StoreBannerCountry::query()->create(['store_banner_id' => $banner->id, 'country_id' => $countryId]);
        }

        $banner->unsetRelation('countryRows');
    }

    public function nextSortOrder(BannerPlacement $placement): int
    {
        return (int) $this->query()->where('placement', $placement->value)->max('sort_order') + 1;
    }

    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
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

    public function count(): int
    {
        return $this->query()->count();
    }
}
