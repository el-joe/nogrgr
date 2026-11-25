<?php

namespace App\Modules\Commerce\Services\Stock;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\DTOs\Cart\StockRequirement;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Repositories\Contracts\StockReservationRepositoryContract;
use App\Shared\Services\BaseService;
use Carbon\CarbonInterface;

/**
 * Stock holds of storefront orders (decision #68):
 *   sellable = on-hand stock − live reservations − committed (paid, unfulfilled) units;
 *   central products count the holds of every store (one shared stock), own products only
 *   the store's own. reserve() locks the product rows, re-checks and writes the holds for
 *   a checkout group; commit() on payment (own stock is deducted at once, central stays
 *   committed until a branch fulfils it); release() on failure, cancellation or timeout.
 */
class StockReservationService extends BaseService
{
    public function __construct(private readonly StockReservationRepositoryContract $reservations) {}

    /**
     * Sellable units per "productId:variantKey" (null = not managed / unlimited).
     *
     * @param  list<int>  $productIds
     * @return array<string, int|null>
     */
    public function sellable(StoreProductSource $source, array $productIds): array
    {
        $levels = $this->reservations->stockLevels($source, $productIds);
        $held = $this->reservations->heldUnits($source, $productIds);

        foreach ($levels as $key => $level) {
            if ($level !== null) {
                $levels[$key] = max(0, $level - ($held[$key] ?? 0));
            }
        }

        return $levels;
    }

    /**
     * @param  list<StockRequirement>  $requirements
     *
     * @throws CommerceException when a line no longer has enough stock
     */
    public function reserve(string $checkoutGroup, array $requirements, CarbonInterface $expiresAt): void
    {
        $done = [];

        try {
            foreach ([StoreProductSource::Central, StoreProductSource::Own] as $source) {
                $lines = array_values(array_filter($requirements, static fn (StockRequirement $line): bool => $line->source === $source));

                if ($lines !== []) {
                    $this->reserveSource($source, $checkoutGroup, $lines, $expiresAt);
                    $done[] = $source;
                }
            }
        } catch (CommerceException $exception) {
            if ($done !== []) {
                $this->release($checkoutGroup);
            }

            throw $exception;
        }
    }

    /**
     * Payment captured (or COD confirmed): holds become sales.
     */
    public function commit(string $checkoutGroup): void
    {
        foreach ($this->reservations->forGroup($checkoutGroup, StockReservationStatus::Reserved) as $reservation) {
            if ($reservation->product_source === StoreProductSource::Own) {
                $this->reservations->decrementOwnStock($reservation->product_id, $reservation->variant_key, $reservation->quantity);
                $this->reservations->setStatus($reservation, StockReservationStatus::Deducted);

                continue;
            }

            $this->reservations->setStatus($reservation, StockReservationStatus::Committed);
        }
    }

    /**
     * Frees the live holds of a group (or of one of its orders).
     */
    public function release(string $checkoutGroup, ?string $orderUuid = null): void
    {
        foreach ($this->reservations->forGroup($checkoutGroup) as $reservation) {
            $live = in_array($reservation->status, [StockReservationStatus::Reserved, StockReservationStatus::Committed], true);

            if ($live && ($orderUuid === null || $reservation->order_uuid === $orderUuid)) {
                $this->reservations->setStatus($reservation, StockReservationStatus::Released);
            }
        }
    }

    /**
     * @param  list<StockRequirement>  $lines
     */
    private function reserveSource(StoreProductSource $source, string $checkoutGroup, array $lines, CarbonInterface $expiresAt): void
    {
        $productIds = array_values(array_unique(array_map(static fn (StockRequirement $line): int => $line->productId, $lines)));
        sort($productIds);

        $this->transaction(function () use ($source, $checkoutGroup, $lines, $productIds, $expiresAt): void {
            $this->reservations->lockProducts($source, $productIds);
            $sellable = $this->sellable($source, $productIds);
            $needed = [];

            foreach ($lines as $line) {
                $needed[$line->key()] = ($needed[$line->key()] ?? 0) + $line->quantity;
            }

            foreach ($needed as $key => $units) {
                if (array_key_exists($key, $sellable) && $sellable[$key] !== null && $sellable[$key] < $units) {
                    throw CommerceException::blocked(CheckoutBlock::StockChanged);
                }
            }

            foreach ($lines as $line) {
                $this->reservations->create([
                    'checkout_group' => $checkoutGroup,
                    'order_uuid' => $line->orderUuid,
                    'product_source' => $source,
                    'product_id' => $line->productId,
                    'variant_key' => $line->variantKey,
                    'quantity' => $line->quantity,
                    'status' => StockReservationStatus::Reserved,
                    'expires_at' => $expiresAt,
                ]);
            }
        }, $this->reservations->connectionName($source));
    }
}
