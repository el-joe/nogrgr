<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\PayoutMethod;
use App\Modules\Billing\Enums\PayoutStatus;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A platform → store payout (ADM-052 / VEN-064). Paid → the ledger debits what the
 * platform owes the store; a cancelled paid payout is reversed. `payee` snapshots the
 * store's bank details on file (KYC profile) at creation.
 *
 * @property int $id
 * @property string|null $reference
 * @property int $tenant_id
 * @property int $amount
 * @property string $currency
 * @property PayoutMethod $method
 * @property PayoutStatus $status
 * @property Carbon|null $scheduled_for
 * @property string|null $transaction_reference
 * @property string|null $note
 * @property bool $notify_vendor
 * @property array<string, string|null>|null $payee
 * @property int|null $created_by_id
 * @property string|null $created_by_name
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantPayout extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['reference', 'tenant_id', 'amount', 'currency', 'method', 'status', 'scheduled_for', 'transaction_reference', 'note', 'notify_vendor', 'payee', 'created_by_id', 'created_by_name', 'paid_at', 'cancelled_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'amount' => 'integer',
            'method' => PayoutMethod::class,
            'status' => PayoutStatus::class,
            'scheduled_for' => 'date',
            'notify_vendor' => 'boolean',
            'payee' => 'array',
            'created_by_id' => 'integer',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function money(): Money
    {
        return Money::ofMinor($this->amount, $this->currency);
    }
}
