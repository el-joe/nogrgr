<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Support\Str;

/**
 * Central coupons and flash sales (decision #65 b merge model: central rows apply to
 * all stores, stores add their own and hide central ones). Old central promotions were
 * platform-wide, so store_scope = all; country pivots are re-keyed.
 */
class PromotionsStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Promotions;
    }

    public function run(LegacyContext $context): void
    {
        $this->coupons($context);
        $this->flashSales($context);
    }

    private function coupons(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $countries = $source->table('central_coupon_country')->get()->groupBy('central_coupon_id');

        foreach ($source->table('central_coupons')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'coupons');
            $id = (int) $row->id;
            $type = CouponType::tryFrom((string) $row->type) ?? CouponType::Percentage;
            $code = Str::upper(trim((string) $row->code));
            $couponId = $writer->upsert('coupon', $id, 'coupons', [
                'code' => LegacyUnique::value($writer, 'coupons', 'code', $code, $writer->id('coupon', $id), $id, 40),
                'name' => $row->name !== null ? mb_substr((string) $row->name, 0, 120) : null,
                'type' => $type->value,
                'percentage' => $type === CouponType::Percentage ? min(100, (float) $row->value) : null,
                'amount_minor' => $type === CouponType::Fixed ? LegacyValues::minor($row->value) : null,
                'min_spend_minor' => LegacyValues::minor($row->minimum_spend),
                'first_order_only' => false,
                'starts_at' => $row->start_date,
                'ends_at' => $row->end_date,
                'is_active' => (bool) $row->active,
                'store_scope' => PromotionScope::AllStores->value,
                'created_at' => $row->created_at,
                'deleted_at' => $row->deleted_at,
            ]);

            $writer->syncPivot('coupon_country', 'coupon_id', $couponId, $this->countries($context, $countries->get($id), 'coupon_id', $couponId));
        }
    }

    private function flashSales(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $products = $source->table('central_flash_sale_product')->get()->groupBy('central_flash_sale_id');
        $countries = $source->table('central_flash_sale_country')->get()->groupBy('central_flash_sale_id');

        foreach ($source->table('central_flash_sales')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'flash_sales');
            $id = (int) $row->id;

            if ($row->start_date === null || $row->end_date === null) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'flash_sales', $id, 'Flash sale without start/end date (dates taken from creation, inactive)');
            }

            $saleId = $writer->upsert('flash_sale', $id, 'flash_sales', [
                'title' => ['en' => 'Flash sale '.$id, 'ar' => 'عرض سريع '.$id],
                'discount_percentage' => min(100, (float) $row->discount_percentage),
                'starts_at' => $row->start_date ?? $row->created_at ?? now(),
                'ends_at' => $row->end_date ?? $row->start_date ?? $row->created_at ?? now(),
                'is_active' => (bool) $row->active && $row->start_date !== null && $row->end_date !== null,
                'banner_path' => null,
                'store_scope' => PromotionScope::AllStores->value,
                'created_at' => $row->created_at,
                'deleted_at' => $row->deleted_at,
            ]);

            $rows = [];

            foreach ($products->get($id, collect()) as $pivot) {
                $productId = $writer->id('product', (int) $pivot->product_id);

                if ($productId === null) {
                    $context->report->issue(LegacyIssueLevel::Warning, 'central', 'flash_sales', $id, 'Flash sale product missing (link dropped)');

                    continue;
                }

                $rows[$productId] = ['flash_sale_id' => $saleId, 'product_id' => $productId];
            }

            $writer->syncPivot('flash_sale_product', 'flash_sale_id', $saleId, array_values($rows));
            $writer->syncPivot('flash_sale_country', 'flash_sale_id', $saleId, $this->countries($context, $countries->get($id), 'flash_sale_id', $saleId));
        }
    }

    /**
     * @return list<array<string, int>>
     */
    private function countries(LegacyContext $context, ?iterable $pivots, string $column, int $ownerId): array
    {
        $rows = [];

        foreach ($pivots ?? [] as $pivot) {
            $country = $context->central->id('country', (int) $pivot->country_id);

            if ($country !== null) {
                $rows[$country] = [$column => $ownerId, 'country_id' => $country];
            }
        }

        return array_values($rows);
    }
}
