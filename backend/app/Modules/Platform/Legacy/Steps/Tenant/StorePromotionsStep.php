<?php

namespace App\Modules\Platform\Legacy\Steps\Tenant;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Support\Str;

/**
 * Store-own coupons and flash sales (decision #65 b merge model). Old store copies of
 * central promotions (`central_coupon_id` / `central_flash_sale_id`) are NOT migrated:
 * central rows already reach every store through the merge reader.
 */
class StorePromotionsStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::StorePromotions;
    }

    public function run(LegacyContext $context): void
    {
        $this->coupons($context);
        $this->flashSales($context);
    }

    private function coupons(LegacyContext $context): void
    {
        $source = $context->storeSource();
        $writer = $context->storeWriter();
        $scope = $context->scope();

        if (! $context->source->hasTable($source, 'coupons')) {
            return;
        }

        foreach ($source->table('coupons')->orderBy('id')->get() as $row) {
            $context->report->read($scope, 'coupons');

            if (($row->central_coupon_id ?? null) !== null) {
                $context->report->skipped($scope, 'coupons');

                continue;
            }

            $type = CouponType::tryFrom((string) $row->type) ?? CouponType::Percentage;
            $id = (int) $row->id;

            $writer->upsert('store_coupon', $id, 'store_coupons', [
                'code' => LegacyUnique::value($writer, 'store_coupons', 'code', Str::upper(trim((string) $row->code)), $writer->id('store_coupon', $id), $id, 40),
                'type' => $type->value,
                'percentage' => $type === CouponType::Percentage ? min(100, (float) $row->value) : null,
                'amount_minor' => $type === CouponType::Fixed ? LegacyValues::minor($row->value) : null,
                'min_spend_minor' => LegacyValues::minor($row->minimum_spend ?? 0),
                'first_order_only' => false,
                'starts_at' => $row->start_date,
                'ends_at' => $row->end_date,
                'is_active' => true,
                'created_at' => $row->created_at,
            ]);
        }
    }

    private function flashSales(LegacyContext $context): void
    {
        $source = $context->storeSource();
        $writer = $context->storeWriter();
        $scope = $context->scope();

        if (! $context->source->hasTable($source, 'flash_sales')) {
            return;
        }

        $pivot = $context->source->hasTable($source, 'flash_sale_product') ? $source->table('flash_sale_product')->get()->groupBy('flash_sale_id') : collect();

        foreach ($source->table('flash_sales')->orderBy('id')->get() as $row) {
            $context->report->read($scope, 'flash_sales');

            if (($row->central_flash_sale_id ?? null) !== null) {
                $context->report->skipped($scope, 'flash_sales');

                continue;
            }

            $id = (int) $row->id;
            $saleId = $writer->upsert('store_flash_sale', $id, 'store_flash_sales', [
                'title' => ['en' => 'Flash sale '.$id, 'ar' => 'عرض سريع '.$id],
                'discount_percentage' => min(100, (float) $row->discount_percentage),
                'starts_at' => $row->start_date ?? $row->created_at ?? now(),
                'ends_at' => $row->end_date ?? $row->start_date ?? $row->created_at ?? now(),
                'is_active' => (bool) $row->active,
                'created_at' => $row->created_at,
            ]);

            $products = $pivot->get($id, collect())->pluck('product_id')->push($row->product_id ?? null)->filter()->unique();
            $rows = [];

            foreach ($products as $oldProduct) {
                $storeProductId = $writer->id('tenant_product', (int) $oldProduct);
                $store = $storeProductId !== null && $storeProductId > 0 ? $writer->connection()->table('store_products')->where('id', $storeProductId)->first(['source', 'central_product_id']) : null;

                if ($store === null) {
                    continue;
                }

                $central = $store->source === StoreProductSource::Central->value;
                $rows[] = ['store_flash_sale_id' => $saleId, 'product_source' => $store->source, 'product_id' => $central ? (int) $store->central_product_id : (int) $storeProductId];
            }

            $writer->syncPivot('store_flash_sale_products', 'store_flash_sale_id', $saleId, $rows);
        }
    }
}
