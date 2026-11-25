<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\MarkupData;
use App\Modules\Catalog\DTOs\Vendor\StorePriceRow;
use App\Modules\Catalog\Enums\PriceListRunStatus;
use App\Modules\Catalog\Enums\StoreProductChange;
use App\Modules\Catalog\Events\Store\StoreProductsChanged;
use App\Modules\Catalog\Jobs\ApplyStorePriceList;
use App\Modules\Catalog\Models\StorePriceListRun;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StorePriceListRunRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Shared\Services\BaseService;
use Throwable;

/**
 * Price list modal (VEN-010a): preview a markup on one product, then apply it to one or
 * many imported products through a queued run with progress (ApplyStorePriceList).
 */
class StorePriceListService extends BaseService
{
    public function __construct(
        private readonly StoreProductRepositoryContract $store,
        private readonly StorePriceListRunRepositoryContract $runs,
        private readonly StoreProductPricingService $pricing,
    ) {}

    /**
     * @return list<StorePriceRow>
     */
    public function preview(StoreProduct $product, ?MarkupData $markup = null): array
    {
        return $this->pricing->table($product, $markup);
    }

    /**
     * @param  list<int>  $productIds  store product ids (imported products only)
     */
    public function start(array $productIds, MarkupData $markup, ?int $staffId): StorePriceListRun
    {
        $ids = $this->store->query()->central()->whereIn('id', $productIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        /** @var StorePriceListRun $run */
        $run = $this->runs->create([
            'status' => PriceListRunStatus::Queued,
            'product_ids' => $ids,
            'profit_type' => $markup->profitType,
            'profit_value' => $markup->profitValue,
            'country_id' => $markup->countryId,
            'variant_id' => count($ids) === 1 ? $markup->variantId : null,
            'total' => count($ids),
            'processed' => 0,
            'started_by' => $staffId,
        ]);

        ApplyStorePriceList::dispatch($run->id);

        return $run;
    }

    public function find(string $id): ?StorePriceListRun
    {
        /** @var StorePriceListRun|null $run */
        $run = $this->runs->find($id);

        return $run;
    }

    /**
     * Job body: applies the markup product by product, recording progress.
     */
    public function process(StorePriceListRun $run): void
    {
        if ($run->status->isFinished()) {
            return;
        }

        $run->forceFill(['status' => PriceListRunStatus::Running])->save();
        $markup = new MarkupData($run->profit_type, (float) $run->profit_value, $run->country_id, $run->variant_id);
        $processed = 0;

        try {
            foreach (array_chunk($run->product_ids, 50) as $chunk) {
                $products = $this->store->query()->central()->whereIn('id', $chunk)->get();

                foreach ($products as $product) {
                    $this->transaction(fn () => $this->pricing->applyMarkup($product, $markup), $product->getConnectionName());
                    $this->runs->advance($run, ++$processed);
                }
            }
        } catch (Throwable $exception) {
            $run->forceFill(['status' => PriceListRunStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 250), 'finished_at' => now()])->save();

            throw $exception;
        }

        $run->forceFill(['status' => PriceListRunStatus::Completed, 'processed' => $run->total, 'finished_at' => now()])->save();
        event(new StoreProductsChanged(StoreCatalogService::tenantId(), StoreProductChange::Prices, $run->product_ids));
    }
}
