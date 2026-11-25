<?php

namespace App\Modules\AfterSales\Models;

use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A refund of a store order (central, tenant scoped): cancellation, return or manual,
 * paid back through the gateway that captured the order or marked as paid manually.
 *
 * @property int $id
 * @property string $reference
 * @property int $tenant_id
 * @property string $order_uuid
 * @property string $order_number
 * @property int|null $return_request_id
 * @property RefundSource $source
 * @property string $reason
 * @property string $currency
 * @property int $items_amount
 * @property int $shipping_amount
 * @property int $return_fee
 * @property int $amount
 * @property RefundMethod $method
 * @property string|null $gateway
 * @property PaymentSource|null $payment_source
 * @property string|null $original_transaction_id
 * @property string|null $gateway_refund_id
 * @property RefundStatus $status
 * @property string|null $failure_reason
 * @property bool $restock
 * @property ActorType $requested_by_type
 * @property int|null $requested_by_id
 * @property ActorType|null $processed_by_type
 * @property int|null $processed_by_id
 * @property string|null $processed_by_name
 * @property string|null $notes
 * @property int $attempts
 * @property Carbon $requested_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ReturnRequest|null $returnRequest
 * @property-read Collection<int, RefundAttempt> $attemptLog
 */
class Refund extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference', 'tenant_id', 'order_uuid', 'order_number', 'return_request_id', 'source', 'reason', 'currency', 'items_amount',
        'shipping_amount', 'return_fee', 'amount', 'method', 'gateway', 'payment_source', 'original_transaction_id', 'gateway_refund_id',
        'status', 'failure_reason', 'restock', 'requested_by_type', 'requested_by_id', 'processed_by_type', 'processed_by_id',
        'processed_by_name', 'notes', 'attempts', 'requested_at', 'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'return_request_id' => 'integer',
            'source' => RefundSource::class,
            'items_amount' => 'integer',
            'shipping_amount' => 'integer',
            'return_fee' => 'integer',
            'amount' => 'integer',
            'method' => RefundMethod::class,
            'payment_source' => PaymentSource::class,
            'status' => RefundStatus::class,
            'restock' => 'boolean',
            'requested_by_type' => ActorType::class,
            'requested_by_id' => 'integer',
            'processed_by_type' => ActorType::class,
            'processed_by_id' => 'integer',
            'attempts' => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function money(string $column = 'amount'): Money
    {
        return Money::ofMinor((int) $this->getAttribute($column), $this->currency);
    }

    /**
     * @return BelongsTo<ReturnRequest, $this>
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /**
     * @return HasMany<RefundAttempt, $this>
     */
    public function attemptLog(): HasMany
    {
        return $this->hasMany(RefundAttempt::class)->orderByDesc('id');
    }
}
