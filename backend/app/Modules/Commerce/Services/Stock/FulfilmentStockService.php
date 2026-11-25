<?php

namespace App\Modules\Commerce\Services\Stock;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Repositories\Contracts\StockReservationRepositoryContract;
use App\Modules\Inventory\Services\StockService;
use App\Shared\Services\BaseService;

/**
 * Physical stock moves of fulfilment (decision #69):
 *   shipping a paid catalog order turns its central holds "committed" into a real branch
 *   decrement (Inventory StockService::deduct) and marks them "deducted";
 *   a returned unit that passes inspection is restocked (central → a branch, own → the
 *   store product / variant stock).
 */
class FulfilmentStockService extends BaseService
{
    public function __construct(
        private readonly StockReservationRepositoryContract $reservations,
        private readonly StockService $stock,
    ) {}

    /**
     * @return int|null the branch the central lines left from
     */
    public function shipOrder(Order $order, ?int $branchId = null): ?int
    {
        $used = $branchId;

        foreach ($this->reservations->forOrder($order->uuid, StockReservationStatus::Committed) as $hold) {
            if ($hold->product_source !== StoreProductSource::Central) {
                continue;
            }

            $branch = $this->stock->pickBranch($hold->product_id, $hold->variant_key, $hold->quantity, $branchId ?? $used);
            $this->stock->deduct($branch, $hold->product_id, $hold->variant_key, $hold->quantity);
            $this->reservations->setStatus($hold, StockReservationStatus::Deducted);
            $used ??= $branch;
        }

        return $used;
    }

    /**
     * @return int|null the branch that received central units (null for own products)
     */
    public function restockLine(Order $order, OrderItem $item, int $quantity, ?int $branchId = null): ?int
    {
        if ($quantity < 1) {
            return null;
        }

        if ($item->product_source === StoreProductSource::Own) {
            $this->reservations->incrementOwnStock($item->store_product_id, $item->variant_key, $quantity);

            return null;
        }

        $branch = $branchId ?? $order->fulfilment_branch_id ?? $this->stock->pickBranch($item->product_id, $item->variant_key, 0);
        $this->stock->restock($branch, $item->product_id, $item->variant_key, $quantity);

        return $branch;
    }
}
