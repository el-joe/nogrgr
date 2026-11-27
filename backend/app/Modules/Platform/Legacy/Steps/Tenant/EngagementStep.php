<?php

namespace App\Modules\Platform\Legacy\Steps\Tenant;

use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;

/**
 * Product reviews (old `product_rates`) and favourites. New reviews must belong to a
 * purchase: the customer's latest migrated order line of that product is used; ratings
 * without one (or anonymous) are reported and skipped.
 */
class EngagementStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Engagement;
    }

    public function run(LegacyContext $context): void
    {
        $this->reviews($context);
        $this->favorites($context);
    }

    private function reviews(LegacyContext $context): void
    {
        $source = $context->storeSource();
        $writer = $context->storeWriter();
        $scope = $context->scope();

        if (! $context->source->hasTable($source, 'product_rates')) {
            return;
        }

        foreach ($source->table('product_rates')->orderBy('id')->lazyById(500) as $row) {
            $context->report->read($scope, 'reviews');
            $productId = $writer->id('tenant_product', (int) $row->product_id);
            $customerId = $row->customer_id !== null ? $writer->id('customer', (int) $row->customer_id) : null;
            $line = $productId !== null && $customerId !== null && $productId > 0 && $customerId > 0
                ? $writer->connection()->table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.customer_id', $customerId)->where('order_items.store_product_id', $productId)
                    ->orderByDesc('orders.id')->first(['order_items.id as item_id', 'orders.id as order_id', 'orders.customer_name'])
                : null;

            if ($line === null && ! $context->dryRun()) {
                $context->report->issue(LegacyIssueLevel::Info, $scope, 'reviews', (int) $row->id, 'Rating without a matching purchase or customer (skipped)');
                $context->report->skipped($scope, 'reviews');

                continue;
            }

            $writer->upsert('review', (int) $row->id, 'product_reviews', [
                'store_product_id' => $productId,
                'customer_id' => $customerId,
                'order_id' => $line->order_id ?? 0,
                'order_item_id' => $line->item_id ?? 0,
                'rating' => max(1, min(5, (int) $row->stars)),
                'comment' => (string) ($row->comment ?? ''),
                'author_name' => mb_substr((string) ($line->customer_name ?? 'Customer'), 0, 120),
                'status' => ReviewStatus::Approved->value,
                'moderated_at' => $row->created_at,
                'created_at' => $row->created_at,
            ]);
        }
    }

    private function favorites(LegacyContext $context): void
    {
        $source = $context->storeSource();
        $writer = $context->storeWriter();
        $scope = $context->scope();

        if (! $context->source->hasTable($source, 'favorites')) {
            return;
        }

        foreach ($source->table('favorites')->orderBy('id')->lazyById(500) as $row) {
            $context->report->read($scope, 'favorites');
            $productId = $writer->id('tenant_product', (int) $row->product_id);
            $customerId = $writer->id('customer', (int) $row->customer_id);

            if ($productId === null || $customerId === null) {
                $context->report->issue(LegacyIssueLevel::Info, $scope, 'favorites', (int) $row->id, 'Favourite of a missing product or customer (skipped)');
                $context->report->skipped($scope, 'favorites');

                continue;
            }

            $writer->upsert('favorite', (int) $row->id, 'customer_favorites', [
                'customer_id' => $customerId,
                'store_product_id' => $productId,
                'created_at' => $row->created_at,
                'updated_at' => false,
            ], ['customer_id' => $customerId, 'store_product_id' => $productId]);
        }
    }
}
