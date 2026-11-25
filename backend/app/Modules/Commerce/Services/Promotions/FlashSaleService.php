<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\DTOs\FlashSaleData;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Events\PromotionsChanged;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Repositories\Contracts\FlashSaleRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Central flash sales (ADM-026 / 026a): products (central), %, dates, countries, stores,
 * optional banner on the central `public` disk (promotions/flash-sales/…).
 */
class FlashSaleService
{
    public const BANNER_DIRECTORY = 'promotions/flash-sales';

    public function __construct(private readonly FlashSaleRepositoryContract $sales) {}

    public function create(FlashSaleData $data): FlashSale
    {
        $sale = DB::connection((new FlashSale)->getConnectionName())->transaction(function () use ($data): FlashSale {
            /** @var FlashSale $sale */
            $sale = $this->sales->create([...$data->attributes(), 'store_scope' => $data->storeScope]);
            $this->sales->syncTargets($sale, $data->productIds, $data->countryIds, $data->tenantIds);

            return $sale;
        });

        $this->storeBanner($sale, $data);
        $this->changed($sale);

        return $sale;
    }

    public function update(FlashSale $sale, FlashSaleData $data): FlashSale
    {
        DB::connection($sale->getConnectionName())->transaction(function () use ($sale, $data): void {
            $this->sales->update($sale, [...$data->attributes(), 'store_scope' => $data->storeScope]);
            $this->sales->syncTargets($sale, $data->productIds, $data->countryIds, $data->tenantIds);
        });

        $this->storeBanner($sale, $data);
        $this->changed($sale);

        return $sale;
    }

    public function setActive(FlashSale $sale, bool $active): void
    {
        $this->sales->update($sale, ['is_active' => $active]);
        $this->changed($sale);
    }

    public function delete(FlashSale $sale): void
    {
        $this->sales->delete($sale);
        $this->changed($sale);
    }

    /**
     * @return array{live: int, scheduled: int, ended: int}
     */
    public function tabCounts(): array
    {
        return $this->sales->tabCounts();
    }

    private function storeBanner(FlashSale $sale, FlashSaleData $data): void
    {
        $old = $sale->banner_path;

        if ($data->banner !== null) {
            $path = $data->banner->store(self::BANNER_DIRECTORY, 'public');
            $this->sales->update($sale, ['banner_path' => $path === false ? null : $path]);
        } elseif ($data->removeBanner) {
            $this->sales->update($sale, ['banner_path' => null]);
        } else {
            return;
        }

        if ($old !== null && $old !== $sale->banner_path) {
            Storage::disk('public')->delete($old);
        }
    }

    private function changed(FlashSale $sale): void
    {
        PromotionsChanged::dispatch(PromotionKind::FlashSale, DiscountSource::Central, $sale->id);
    }
}
