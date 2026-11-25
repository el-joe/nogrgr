<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One use of a store coupon by an order.
 *
 * @property int $id
 * @property int $store_coupon_id
 * @property int|null $customer_id
 * @property string $order_reference
 * @property int $discount_minor
 * @property Carbon $redeemed_at
 */
class StoreCouponRedemption extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['store_coupon_id', 'customer_id', 'order_reference', 'discount_minor', 'redeemed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['redeemed_at' => 'datetime', 'discount_minor' => 'integer'];
    }
}
