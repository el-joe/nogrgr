<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\CancellationReason;
use App\Modules\Commerce\Enums\CollectedBy;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A placed storefront order (tenant DB) with its full money snapshot: every
 * OrderComposer / OrderPricing output (USD minor units), addresses, shipping quote and
 * delivery estimate. Orders of one checkout (a split mixed cart) share checkout_group and
 * are paid by one payment.
 *
 * @property int $id
 * @property string $uuid
 * @property string $number
 * @property string $checkout_group
 * @property OrderKind $kind
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property int|null $customer_id
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property int|null $country_id
 * @property array<string, mixed>|null $shipping_address
 * @property array<string, mixed>|null $billing_address
 * @property string $currency
 * @property int $items_grand_total
 * @property int $display_subtotal
 * @property int $flash_discount
 * @property int $tax
 * @property int $shipping_charge
 * @property int $coupon_discount
 * @property DiscountSource|null $coupon_source
 * @property string|null $coupon_code
 * @property int|null $coupon_id
 * @property int $grand_total
 * @property int $platform_profit
 * @property int $vendor_profit
 * @property array<string, mixed> $totals
 * @property array<string, mixed>|null $shipping_quote
 * @property int|null $shipping_company_id
 * @property string|null $shipping_company_name
 * @property Carbon|null $delivery_min_date
 * @property Carbon|null $delivery_max_date
 * @property array<string, mixed>|null $delivery_estimate
 * @property string|null $payment_gateway
 * @property PaymentSource|null $payment_source
 * @property string|null $display_currency
 * @property string|null $display_rate
 * @property string|null $locale
 * @property string|null $tracking_number
 * @property string|null $cancel_reason
 * @property Carbon|null $reservation_expires_at
 * @property Carbon|null $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property FulfilmentStatus $fulfilment_status
 * @property int|null $fulfilment_branch_id
 * @property CancellationReason|null $cancel_reason_code
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderTrackingEvent> $trackingEvents
 * @property-read Collection<int, OrderStatusHistory> $history
 */
class Order extends Model
{
    /**
     * Explicit storage format: admin pages read store orders after leaving the store's
     * tenant context, where the "tenant" connection (default date format) is gone.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid', 'number', 'checkout_group', 'kind', 'status', 'payment_status', 'customer_id', 'customer_name',
        'customer_email', 'customer_phone', 'country_id', 'shipping_address', 'billing_address', 'currency',
        'items_grand_total', 'display_subtotal', 'flash_discount', 'tax', 'shipping_charge', 'coupon_discount',
        'coupon_source', 'coupon_code', 'coupon_id', 'grand_total', 'platform_profit', 'vendor_profit', 'totals',
        'shipping_quote', 'shipping_company_id', 'shipping_company_name', 'delivery_min_date', 'delivery_max_date',
        'delivery_estimate', 'payment_gateway', 'payment_source', 'display_currency', 'display_rate', 'locale',
        'tracking_number', 'cancel_reason', 'reservation_expires_at', 'placed_at', 'paid_at', 'shipped_at',
        'delivered_at', 'completed_at', 'cancelled_at', 'refunded_at', 'returned_at', 'fulfilment_status',
        'fulfilment_branch_id', 'cancel_reason_code',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['fulfilment_status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OrderKind::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'coupon_source' => DiscountSource::class,
            'payment_source' => PaymentSource::class,
            'fulfilment_status' => FulfilmentStatus::class,
            'cancel_reason_code' => CancellationReason::class,
            'fulfilment_branch_id' => 'integer',
            'customer_id' => 'integer',
            'country_id' => 'integer',
            'coupon_id' => 'integer',
            'shipping_company_id' => 'integer',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'totals' => 'array',
            'shipping_quote' => 'array',
            'delivery_estimate' => 'array',
            'items_grand_total' => 'integer',
            'display_subtotal' => 'integer',
            'flash_discount' => 'integer',
            'tax' => 'integer',
            'shipping_charge' => 'integer',
            'coupon_discount' => 'integer',
            'grand_total' => 'integer',
            'platform_profit' => 'integer',
            'vendor_profit' => 'integer',
            'delivery_min_date' => 'date',
            'delivery_max_date' => 'date',
            'reservation_expires_at' => 'datetime',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * A stored money column as Money (USD).
     */
    public function money(string $column): Money
    {
        return Money::ofMinor((int) $this->getAttribute($column), $this->currency ?: Money::BASE_CURRENCY);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderTrackingEvent, $this>
     */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(OrderTrackingEvent::class)->orderByDesc('id');
    }

    /**
     * Own-products orders are merchant-fulfilled: the platform never ships or changes them.
     */
    public function isOwnProducts(): bool
    {
        return $this->kind === OrderKind::Own;
    }

    public function collectedBy(): CollectedBy
    {
        return CollectedBy::forOrder($this->payment_source, $this->payment_status);
    }

    /**
     * Money was taken for the order (gateway capture; COD is collected on delivery).
     */
    public function isPaidOnline(): bool
    {
        return $this->paid_at !== null && in_array($this->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true);
    }
}
