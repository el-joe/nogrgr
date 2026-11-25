<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\DTOs\FlashSaleData;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Events\PromotionsChanged;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Repositories\Contracts\StoreFlashSaleRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Store-owned flash sales (VEN-041 service API). Products are typed refs (central or
 * own); the banner lives on the store's tenant-suffixed `public` disk.
 */
class StoreFlashSaleService
{
    public function __construct(private readonly StoreFlashSaleRepositoryContract $sales) {}

    public function create(FlashSaleData $data): StoreFlashSale
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        $sale = DB::connection((new StoreFlashSale)->getConnectionName())->transaction(function () use ($data): StoreFlashSale {
            /** @var StoreFlashSale $sale */
            $sale = $this->sales->create($data->attributes());
            $this->sales->syncTargets($sale, $data->products, $data->countryIds);

            return $sale;
        });

        $this->storeBanner($sale, $data);
        PromotionsChanged::dispatch(PromotionKind::FlashSale, DiscountSource::Vendor, $sale->id, $tenantId);

        return $sale;
    }

    public function update(StoreFlashSale $sale, FlashSaleData $data): StoreFlashSale
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        DB::connection($sale->getConnectionName())->transaction(function () use ($sale, $data): void {
            $this->sales->update($sale, $data->attributes());
            $this->sales->syncTargets($sale, $data->products, $data->countryIds);
        });

        $this->storeBanner($sale, $data);
        PromotionsChanged::dispatch(PromotionKind::FlashSale, DiscountSource::Vendor, $sale->id, $tenantId);

        return $sale;
    }

    public function delete(StoreFlashSale $sale): void
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();
        $this->sales->delete($sale);

        PromotionsChanged::dispatch(PromotionKind::FlashSale, DiscountSource::Vendor, $sale->id, $tenantId);
    }

    /**
     * VEN-041b: adds a product to one of the store's sales; false when already there.
     */
    public function attachProduct(StoreFlashSale $sale, ProductRef $product): bool
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();
        $attached = $this->sales->attachProduct($sale, $product);

        if ($attached) {
            PromotionsChanged::dispatch(PromotionKind::FlashSale, DiscountSource::Vendor, $sale->id, $tenantId);
        }

        return $attached;
    }

    private function storeBanner(StoreFlashSale $sale, FlashSaleData $data): void
    {
        $old = $sale->banner_path;

        if ($data->banner !== null) {
            $path = $data->banner->store(FlashSaleService::BANNER_DIRECTORY, 'public');
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
}
