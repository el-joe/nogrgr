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
 * @property int|null $order_id
 * @property string|null $customer_name
 * @property string|null $customer_city
 * @property string|null $payment_source
 * @property string $fulfilment_status
 * @property int|null $shipping_company_id
 * @property string|null $shipping_company_name
 * @property string|null $tracking_number
 * @property int $weight_grams
 * @property int|null $fulfilment_branch_id
 * @property Carbon|null $delivery_min_date
 * @property Carbon|null $delivery_max_date
 * @property string|null $customer_email
 * @property string|null $coupon_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        'order_id', 'customer_name', 'customer_city', 'payment_source', 'fulfilment_status', 'shipping_company_id', 'shipping_company_name',
        'tracking_number', 'weight_grams', 'fulfilment_branch_id', 'delivery_min_date', 'delivery_max_date', 'customer_email', 'coupon_code',
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
            'order_id' => 'integer',
            'shipping_company_id' => 'integer',
            'weight_grams' => 'integer',
            'fulfilment_branch_id' => 'integer',
            'delivery_min_date' => 'date',
            'delivery_max_date' => 'date',
        ];
    }

    /**
     * Own-products orders are merchant-fulfilled: the platform only reads them.
     */
    public function isOwnProducts(): bool
    {
        return $this->kind === 'own';
    }
}
