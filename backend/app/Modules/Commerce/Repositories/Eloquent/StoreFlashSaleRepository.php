<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Models\StoreFlashSaleCountry;
use App\Modules\Commerce\Models\StoreFlashSaleProduct;
use App\Modules\Commerce\Repositories\Contracts\StoreFlashSaleRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StoreFlashSale>
 */
class StoreFlashSaleRepository extends BaseRepository implements StoreFlashSaleRepositoryContract
{
    protected function model(): string
    {
        return StoreFlashSale::class;
    }

    public function listing(?array $products = null, bool $activeOnly = false): Collection
    {
        $now = now();

        return $this->query()
            ->with(['productRows', 'countryRows'])
            ->when($products !== null, fn (Builder $query) => $query->whereHas('productRows', function (Builder $rows) use ($products): void {
                $rows->where(function (Builder $any) use ($products): void {
                    $any->whereRaw('1 = 0');

                    foreach ((array) $products as $ref) {
                        $any->orWhere(fn (Builder $one) => $one->where('product_source', $ref->source->value)->where('product_id', $ref->id));
                    }
                });
            }))
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now))
            ->orderBy('id')
            ->get();
    }

    public function syncTargets(StoreFlashSale $sale, array $products, array $countryIds): void
    {
        StoreFlashSaleProduct::query()->where('store_flash_sale_id', $sale->id)->delete();
        StoreFlashSaleCountry::query()->where('store_flash_sale_id', $sale->id)->delete();

        $rows = [];

        foreach ($products as $ref) {
            $rows[$ref->key()] = ['store_flash_sale_id' => $sale->id, 'product_source' => $ref->source->value, 'product_id' => $ref->id];
        }

        if ($rows !== []) {
            StoreFlashSaleProduct::query()->insert(array_values($rows));
        }

        $countries = array_map(static fn (int $id): array => ['store_flash_sale_id' => $sale->id, 'country_id' => $id], array_values(array_unique($countryIds)));

        if ($countries !== []) {
            StoreFlashSaleCountry::query()->insert($countries);
        }

        $sale->unsetRelation('productRows')->unsetRelation('countryRows');
    }
}
