<?php

namespace App\Modules\Commerce\Models\Concerns;

use App\Modules\Commerce\Enums\CouponType;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;

/**
 * Columns shared by central `coupons` and tenant `store_coupons` (USD minor units).
 *
 * @phpstan-require-extends Model
 */
trait CouponFields
{
    /**
     * @return array<string, string>
     */
    protected function couponCasts(): array
    {
        return [
            'type' => CouponType::class,
            'percentage' => 'float',
            'amount_minor' => MoneyCast::class.':USD',
            'max_discount_minor' => MoneyCast::class.':USD',
            'min_spend_minor' => MoneyCast::class.':USD',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'first_order_only' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function minSpend(): Money
    {
        return $this->min_spend_minor ?? Money::zero();
    }
}
