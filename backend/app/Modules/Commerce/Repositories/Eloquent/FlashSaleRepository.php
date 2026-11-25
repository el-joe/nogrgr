<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Repositories\Contracts\FlashSaleRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<FlashSale>
 */
class FlashSaleRepository extends BaseRepository implements FlashSaleRepositoryContract
{
    protected function model(): string
    {
        return FlashSale::class;
    }

    public function syncTargets(FlashSale $sale, array $productIds, array $countryIds, array $tenantIds): void
    {
        $sale->products()->sync($productIds);
        $sale->countries()->sync($countryIds);
        $sale->tenants()->sync($sale->store_scope === PromotionScope::SelectedStores ? $tenantIds : []);
    }

    public function applicableToTenant(int $tenantId, ?array $productIds = null, bool $activeOnly = false): Collection
    {
        $now = now();

        return $this->query()
            ->with(['countries:id', 'products:id'])
            ->when($productIds !== null, fn (Builder $query) => $query->whereHas('products', fn (Builder $products) => $products->whereIn('products.id', $productIds === [] ? [0] : $productIds)))
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now))
            ->where(fn (Builder $query) => $query
                ->where('store_scope', PromotionScope::AllStores->value)
                ->orWhereHas('tenants', fn (Builder $tenants) => $tenants->whereKey($tenantId)))
            ->orderBy('id')
            ->get();
    }

    public function tabCounts(): array
    {
        $now = now();

        return [
            'live' => $this->query()->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)->count(),
            'scheduled' => $this->query()->where('starts_at', '>', $now)->count(),
            'ended' => $this->query()->where('ends_at', '<', $now)->count(),
        ];
    }
}
