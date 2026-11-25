<?php

namespace App\Modules\AfterSales\Models;

use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\Commerce\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Payment log of a refund: one row per gateway call / manual completion / rejection.
 *
 * @property int $id
 * @property int $refund_id
 * @property string|null $gateway
 * @property RefundStatus $status
 * @property int $amount
 * @property string|null $gateway_refund_id
 * @property string|null $error
 * @property array<string, mixed>|null $details
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property Carbon|null $created_at
 */
class RefundAttempt extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['refund_id', 'gateway', 'status', 'amount', 'gateway_refund_id', 'error', 'details', 'actor_type', 'actor_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => RefundStatus::class, 'amount' => 'integer', 'details' => 'array', 'actor_type' => ActorType::class, 'actor_id' => 'integer'];
    }
}
