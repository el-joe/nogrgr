<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\DTOs\Cart\AddCartItemData;
use App\Modules\Commerce\DTOs\Cart\CartContextData;
use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\Cart\CartService;
use App\Shared\Services\BaseService;

/**
 * "Buy again" (APP-015 / SF-ECO-027): every line of an own order goes back into the
 * customer's cart through the normal add-to-cart rules (sold in the country, active,
 * in stock, quantity limits) at today's prices; lines that cannot be added are reported
 * with the reason instead of failing the whole reorder.
 */
class ReorderService extends BaseService
{
    public function __construct(private readonly CartService $carts) {}

    /**
     * @return array{summary: CartSummary, added: list<array{store_product_id: int, variant_id: int|null, quantity: int, name: string}>, skipped: list<array{store_product_id: int, variant_id: int|null, name: string, reason: string|null, message: string}>}
     */
    public function reorder(CartContextData $context, Order $order): array
    {
        $added = [];
        $skipped = [];
        $summary = null;

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $variantId = (int) $item->variant_key > 0 ? (int) $item->variant_key : null;
            $line = ['store_product_id' => (int) $item->store_product_id, 'variant_id' => $variantId, 'name' => (string) $item->name];

            try {
                $summary = $this->carts->add($context, new AddCartItemData((int) $item->store_product_id, $variantId, max(1, (int) $item->quantity)));
                $added[] = $line + ['quantity' => max(1, (int) $item->quantity)];
            } catch (CommerceException $exception) {
                $skipped[] = $line + ['reason' => $exception->reason, 'message' => $exception->getMessage()];
            }
        }

        if ($added === []) {
            throw CommerceException::rule('reorder_unavailable');
        }

        return ['summary' => $summary ?? $this->carts->summary($context), 'added' => $added, 'skipped' => $skipped];
    }
}
