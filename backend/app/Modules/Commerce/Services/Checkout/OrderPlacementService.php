<?php

namespace App\Modules\Commerce\Services\Checkout;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\DTOs\Cart\CartLine;
use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\DTOs\Cart\StockRequirement;
use App\Modules\Commerce\DTOs\Payments\GatewayOption;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Pricing\OrderTotals;
use App\Modules\Commerce\Pricing\PricedLine;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Commerce\Services\Stock\StockReservationService;
use App\Modules\Identity\DTOs\CheckoutAddressSelectionData;
use App\Shared\Services\BaseService;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the order(s) of a validated cart: one per OrderComposer order (catalog order +
 * own companion for a mixed cart, decision #66 a), every formula output snapshotted,
 * stock reserved first (released again when writing fails).
 */
class OrderPlacementService extends BaseService
{
    public function __construct(
        private readonly OrderRepositoryContract $orders,
        private readonly StockReservationService $stock,
    ) {}

    /**
     * @return Collection<int, Order>
     */
    public function place(CartSummary $summary, CheckoutAddressSelectionData $addresses, GatewayOption $gateway): Collection
    {
        $group = (string) Str::uuid();
        $composed = $summary->composed;
        $plan = [];

        foreach ($composed->orders() as $totals) {
            $plan[] = ['uuid' => (string) Str::uuid(), 'totals' => $totals];
        }

        $expiresAt = now()->addMinutes((int) config('commerce.reservation_minutes', 30));
        $this->stock->reserve($group, $this->requirements($summary, $plan), $expiresAt);

        try {
            $placed = $this->transaction(function () use ($summary, $addresses, $gateway, $group, $plan, $expiresAt): Collection {
                $placed = new Collection;

                foreach ($plan as $entry) {
                    $placed->push($this->create($summary, $addresses, $gateway, $group, $entry['uuid'], $entry['totals'], $expiresAt));
                }

                return $placed;
            });
        } catch (Throwable $exception) {
            $this->stock->release($group);

            throw $exception;
        }

        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        foreach ($placed as $order) {
            OrderStatusChanged::dispatch($order, $tenantId, null, OrderStatus::PendingPayment);
            OrderPlaced::dispatch($order, $tenantId);
        }

        return $placed;
    }

    private function create(CartSummary $summary, CheckoutAddressSelectionData $addresses, GatewayOption $gateway, string $group, string $uuid, OrderTotals $totals, DateTimeInterface $expiresAt): Order
    {
        $customer = $addresses->customer;
        $shipping = $addresses->shippingSnapshot();
        $catalog = array_filter($totals->lines, static fn (PricedLine $line): bool => ! $line->own) !== [];
        $delivery = $catalog ? $summary->delivery : null;
        $quote = $summary->composed->shipping;

        $order = $this->orders->createWithItems([
            'uuid' => $uuid,
            'number' => $this->orders->nextNumber(),
            'checkout_group' => $group,
            'kind' => $catalog ? OrderKind::Catalog : OrderKind::Own,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Unpaid,
            'customer_id' => $customer->id,
            'customer_name' => $shipping->fullName ?? $customer->name,
            'customer_email' => $shipping->email ?? $customer->email,
            'customer_phone' => $shipping->phone ?? $customer->phone,
            'country_id' => $summary->countryId,
            'shipping_address' => $shipping?->toArray(),
            'billing_address' => $addresses->billingSnapshot()?->toArray(),
            'currency' => 'USD',
            'items_grand_total' => $totals->itemsGrandTotal->minor,
            'display_subtotal' => $totals->displaySubtotal()->minor,
            'flash_discount' => $totals->flashDiscount()->minor,
            'tax' => $totals->tax->minor,
            'shipping_charge' => $totals->shippingCharge->minor,
            'coupon_discount' => $totals->couponDiscount->minor,
            'coupon_source' => $totals->couponSource,
            'coupon_code' => $summary->composed->coupon?->code,
            'coupon_id' => $summary->composed->coupon?->id,
            'grand_total' => $totals->grandTotal->minor,
            'platform_profit' => $totals->platformProfit->minor,
            'vendor_profit' => $totals->vendorProfit->minor,
            'totals' => $totals->toArray(),
            'shipping_quote' => $catalog ? $quote->toArray() : null,
            'shipping_company_id' => $delivery?->companyId,
            'shipping_company_name' => $delivery?->companyName,
            'delivery_min_date' => $delivery?->minDate,
            'delivery_max_date' => $delivery?->maxDate,
            'delivery_estimate' => $delivery?->toArray(),
            'payment_gateway' => $gateway->code->value,
            'payment_source' => $gateway->source,
            'locale' => app()->getLocale(),
            'reservation_expires_at' => $expiresAt,
            'placed_at' => now(),
        ], array_map(fn (PricedLine $line): array => $this->item($summary->line($line->key), $line), $totals->lines));

        $this->orders->recordHistory($order, null, OrderStatus::PendingPayment, ActorType::Customer, $customer->id, null);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(?CartLine $line, PricedLine $priced): array
    {
        return [
            'line_key' => $priced->key,
            'store_product_id' => $line->item->store_product_id ?? 0,
            'product_source' => $line !== null ? $line->ref->source : StoreProductSource::Own,
            'product_id' => $line->ref->id ?? 0,
            'variant_key' => $line->item->variant_key ?? 0,
            'name' => mb_substr($line->name ?? $priced->key, 0, 255),
            'variant_label' => $line?->variantLabel,
            'sku' => $line?->sku,
            'image_url' => $line?->imageUrl,
            'quantity' => $priced->quantity,
            'cost' => $priced->cost->minor,
            'profit_amount' => $priced->profitAmount->minor,
            'price' => $priced->price->minor,
            'sub_total' => $priced->subTotal->minor,
            'fixed_shipping' => $priced->fixedShipping->minor,
            'discount' => $priced->discount->minor,
            'discount_source' => $priced->discountSource,
            'flash_sale_id' => $priced->discount->isPositive() ? $line?->flash?->flashSaleId : null,
            'tax' => $priced->tax->minor,
            'shipping_fee' => $priced->shippingFee->minor,
            'grand_total' => $priced->grandTotal->minor,
            'weight_grams' => $priced->weightGrams,
        ];
    }

    /**
     * @param  list<array{uuid: string, totals: OrderTotals}>  $plan
     * @return list<StockRequirement>
     */
    private function requirements(CartSummary $summary, array $plan): array
    {
        $requirements = [];

        foreach ($plan as $entry) {
            foreach ($entry['totals']->lines as $priced) {
                $line = $summary->line($priced->key);

                if ($line !== null) {
                    $requirements[] = new StockRequirement($entry['uuid'], $line->ref->source, $line->ref->id, $line->item->variant_key, $priced->quantity);
                }
            }
        }

        return $requirements;
    }
}
