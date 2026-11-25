<?php

namespace App\Modules\Commerce\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One use of a central coupon by an order of a store (usage limits are counted across
 * all stores, the per-customer limit inside the store).
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $tenant_id
 * @property int|null $customer_id
 * @property string $order_reference
 * @property int $discount_minor
 * @property Carbon $redeemed_at
 */
class CouponRedemption extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['coupon_id', 'tenant_id', 'customer_id', 'order_reference', 'discount_minor', 'redeemed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['redeemed_at' => 'datetime', 'discount_minor' => 'integer'];
    }
}
