<?php

namespace App\Modules\Commerce\Http\Resources\StoreApi;

use App\Modules\Commerce\DTOs\Cart\CartLine;
use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\Pricing\OrderTotals;
use App\Modules\Commerce\Pricing\PricedLine;
use App\Modules\Commerce\Support\MoneyPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cart summary for themes and the app: lines (with issues), flash discounts, the coupon
 * verdict, shipping quote / availability, delivery estimate, free-shipping progress and
 * the OrderComposer totals (and the split, for a mixed cart).
 *
 * @property CartSummary $resource
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;
        $composed = $summary->composed;
        $money = app(MoneyPresenter::class);
        $priced = [];

        foreach ($composed->orders() as $order) {
            foreach ($order->lines as $line) {
                $priced[$line->key] = $line;
            }
        }

        $threshold = $summary->freeShippingThreshold;
        $weight = $summary->weightGrams();
        $shipping = $composed->shipping;

        return [
            'token' => $summary->cart?->token,
            'country_id' => $summary->countryId,
            'items_count' => array_sum(array_map(static fn (CartLine $line): int => $line->item->quantity, $summary->lines)),
            'lines' => array_map(fn (CartLine $line): array => $this->line($line, $priced[$line->key()] ?? null, $money), $summary->lines),
            'coupon' => $summary->coupon === null ? null : [
                'code' => $summary->coupon->coupon->code ?? $summary->cart?->coupon_code,
                'applied' => $summary->coupon->accepted(),
                'source' => $summary->coupon->coupon?->source->value,
                'type' => $summary->coupon->coupon?->type->value,
                'message' => $summary->coupon->message(),
                'discount' => $money->present($composed->couponDiscount()),
            ],
            'shipping' => [
                'available' => $shipping->available,
                'free' => $shipping->isFree(),
                'amount' => $money->present($composed->shippingCharge()),
                'zone' => $shipping->zoneName,
                'rate' => $shipping->bandName,
                'reason' => $shipping->reason?->value,
                'message' => $shipping->message(),
                'weight_grams' => $weight,
            ],
            'delivery' => $summary->delivery?->toArray(),
            // Past a free band into a paid or uncovered weight there is nothing to promise: no meter.
            'free_shipping' => $threshold === null || ($weight >= $threshold && ! $shipping->isFree()) ? null : [
                'threshold_grams' => $threshold,
                'weight_grams' => $weight,
                'remaining_grams' => max(0, $threshold - $weight),
                'reached' => $weight >= $threshold,
                'progress' => $threshold > 0 ? min(100, (int) floor($weight * 100 / $threshold)) : 100,
            ],
            'totals' => [
                'subtotal' => $money->present($composed->displaySubtotal()),
                'flash_discount' => $money->present($composed->flashDiscount()),
                'items_total' => $money->present($composed->itemsGrandTotal()),
                'coupon_discount' => $money->present($composed->couponDiscount()),
                'shipping' => $money->present($composed->shippingCharge()),
                'grand_total' => $money->present($composed->grandTotal()),
            ],
            'orders' => array_map(fn (OrderTotals $order): array => [
                'own' => array_filter($order->lines, static fn (PricedLine $line): bool => ! $line->own) === [],
                'lines' => array_map(static fn (PricedLine $line): string => $line->key, $order->lines),
                'items_total' => $money->present($order->itemsGrandTotal),
                'shipping' => $money->present($order->shippingCharge),
                'coupon_discount' => $money->present($order->couponDiscount),
                'grand_total' => $money->present($order->grandTotal),
            ], $composed->orders()),
            'split' => $composed->isMixed(),
            'can_checkout' => $composed->canCheckout() && ! $summary->hasIssues(),
            'display_currency' => $money->displayCurrency(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(CartLine $line, ?PricedLine $priced, MoneyPresenter $money): array
    {
        return [
            'id' => $line->item->id,
            'key' => $line->key(),
            'store_product_id' => $line->item->store_product_id,
            'variant_id' => $line->item->variantId(),
            'own' => $line->isOwn(),
            'name' => $line->name,
            'variant' => $line->variantLabel,
            'sku' => $line->sku,
            'image_url' => $line->imageUrl,
            'quantity' => $line->item->quantity,
            'weight_grams' => $line->input?->weightGrams,
            'available' => $line->available,
            'unit_price' => $line->unitDisplayPrice !== null ? $money->present($line->unitDisplayPrice) : null,
            'subtotal' => $priced !== null ? $money->present($priced->displaySubtotal()) : null,
            'discount' => $priced !== null ? $money->present($priced->discount) : null,
            'total' => $priced !== null ? $money->present($priced->grandTotal) : null,
            'flash_sale' => $line->flash !== null ? ['percentage' => $line->flash->percentage, 'source' => $line->flash->source->value] : null,
            'issue' => $line->issue?->value,
            'message' => $line->issue?->message(),
        ];
    }
}
