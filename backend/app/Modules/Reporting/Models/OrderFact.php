<?php

namespace App\Modules\Reporting\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central reporting row of one store order (fed by Commerce order events).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $order_uuid
 * @property string $order_number
 * @property string $kind
 * @property string $status
 * @property string $payment_status
 * @property int|null $customer_id
 * @property int|null $country_id
 * @property string $currency
 * @property int $items_count
 * @property int $items_grand_total
 * @property int $flash_discount
 * @property int $shipping_charge
 * @property int $coupon_discount
 * @property string|null $coupon_source
 * @property int $grand_total
 * @property int $platform_profit
 * @property int $vendor_profit
 * @property string|null $payment_gateway
 * @property Carbon|null $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 */
class OrderFact extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'order_uuid', 'order_number', 'kind', 'status', 'payment_status', 'customer_id', 'country_id', 'currency',
        'items_count', 'items_grand_total', 'flash_discount', 'shipping_charge', 'coupon_discount', 'coupon_source', 'grand_total',
        'platform_profit', 'vendor_profit', 'payment_gateway', 'placed_at', 'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'customer_id' => 'integer',
            'country_id' => 'integer',
            'items_count' => 'integer',
            'items_grand_total' => 'integer',
            'flash_discount' => 'integer',
            'shipping_charge' => 'integer',
            'coupon_discount' => 'integer',
            'grand_total' => 'integer',
            'platform_profit' => 'integer',
            'vendor_profit' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
