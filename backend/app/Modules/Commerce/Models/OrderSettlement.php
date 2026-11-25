<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\SettlementStatus;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A "Pay platform" payment of a vendor-collected order (ORDER_FORMULAS.md §3 Settlement
 * Payments). Central; paid by the vendor through the vendor-payments gateway.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $order_uuid
 * @property string $order_number
 * @property int $amount
 * @property int $shipping_cost
 * @property int $product_cost
 * @property string $currency
 * @property string $gateway
 * @property int|null $payment_log_id
 * @property SettlementStatus $status
 * @property int|null $paid_by_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrderSettlement extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'order_uuid', 'order_number', 'amount', 'shipping_cost', 'product_cost', 'currency', 'gateway', 'payment_log_id', 'status', 'paid_by_id', 'paid_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'amount' => 'integer',
            'shipping_cost' => 'integer',
            'product_cost' => 'integer',
            'payment_log_id' => 'integer',
            'paid_by_id' => 'integer',
            'status' => SettlementStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function money(string $column = 'amount'): Money
    {
        return Money::ofMinor((int) $this->getAttribute($column), $this->currency);
    }
}
