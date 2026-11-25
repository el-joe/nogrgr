<?php

namespace App\Modules\AfterSales\Models;

use App\Modules\AfterSales\Enums\InspectionResult;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\Commerce\Enums\ActorType;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A return / exchange of units of one store order line (central, tenant scoped). The
 * product name and variant are the order line snapshot; money is USD minor units.
 *
 * @property int $id
 * @property string $reference
 * @property int $tenant_id
 * @property string $order_uuid
 * @property string $order_number
 * @property int $order_item_id
 * @property int|null $store_product_id
 * @property string $product_name
 * @property string|null $variant_label
 * @property int $quantity
 * @property ReturnType $type
 * @property string $reason_code
 * @property bool $seller_fault
 * @property string $description
 * @property ReturnStatus $status
 * @property int|null $customer_id
 * @property string|null $customer_name
 * @property int $items_amount
 * @property int $shipping_amount
 * @property int $return_fee
 * @property int $refund_amount
 * @property InspectionResult|null $inspection_result
 * @property string|null $inspection_condition
 * @property string|null $inspection_notes
 * @property bool $restocked
 * @property int|null $restock_branch_id
 * @property string|null $exchange_tracking_number
 * @property ActorType $created_by_type
 * @property int|null $created_by_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $received_at
 * @property Carbon|null $inspected_at
 * @property Carbon|null $restocked_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $exchange_shipped_at
 * @property Carbon|null $exchanged_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ReturnRequestEvent> $events
 * @property-read Collection<int, ReturnRequestMedia> $media
 * @property-read Collection<int, Refund> $refunds
 */
class ReturnRequest extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference', 'tenant_id', 'order_uuid', 'order_number', 'order_item_id', 'store_product_id', 'product_name', 'variant_label',
        'quantity', 'type', 'reason_code', 'seller_fault', 'description', 'status', 'customer_id', 'customer_name', 'items_amount',
        'shipping_amount', 'return_fee', 'refund_amount', 'inspection_result', 'inspection_condition', 'inspection_notes', 'restocked',
        'restock_branch_id', 'exchange_tracking_number', 'created_by_type', 'created_by_id', 'approved_at', 'received_at', 'inspected_at',
        'restocked_at', 'refunded_at', 'exchange_shipped_at', 'exchanged_at', 'cancelled_at', 'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'order_item_id' => 'integer',
            'store_product_id' => 'integer',
            'quantity' => 'integer',
            'type' => ReturnType::class,
            'seller_fault' => 'boolean',
            'status' => ReturnStatus::class,
            'customer_id' => 'integer',
            'items_amount' => 'integer',
            'shipping_amount' => 'integer',
            'return_fee' => 'integer',
            'refund_amount' => 'integer',
            'inspection_result' => InspectionResult::class,
            'restocked' => 'boolean',
            'restock_branch_id' => 'integer',
            'created_by_type' => ActorType::class,
            'created_by_id' => 'integer',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
            'restocked_at' => 'datetime',
            'refunded_at' => 'datetime',
            'exchange_shipped_at' => 'datetime',
            'exchanged_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function money(string $column): Money
    {
        return Money::ofMinor((int) $this->getAttribute($column));
    }

    /**
     * @return HasMany<ReturnRequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ReturnRequestEvent::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<ReturnRequestMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ReturnRequestMedia::class)->orderBy('id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderByDesc('id');
    }
}
