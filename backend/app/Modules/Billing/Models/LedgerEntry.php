<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\LedgerAccount;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A debit / credit line of a ledger transaction (decision #72). Order-level lines carry
 * the order uuid / number (per-order balances: purchases owed, settlements).
 *
 * @property int $id
 * @property int $ledger_transaction_id
 * @property int $tenant_id
 * @property string|null $order_uuid
 * @property string|null $order_number
 * @property LedgerAccount $account
 * @property int $debit
 * @property int $credit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LedgerTransaction $transaction
 */
class LedgerEntry extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['ledger_transaction_id', 'tenant_id', 'order_uuid', 'order_number', 'account', 'debit', 'credit'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ledger_transaction_id' => 'integer',
            'tenant_id' => 'integer',
            'account' => LedgerAccount::class,
            'debit' => 'integer',
            'credit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }
}
