<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\LedgerDirection;
use App\Modules\Billing\Enums\LedgerSource;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One finance event of a store (decision #72): its balanced ledger entries, the signed
 * effect on what the platform owes the store (`net_amount`) and the running store net
 * after it (`balance_after`). Unique per `idempotency_key` (one posting per source event).
 *
 * @property int $id
 * @property string|null $reference
 * @property int $tenant_id
 * @property LedgerSource $source
 * @property string|null $source_reference
 * @property string|null $order_uuid
 * @property LedgerDirection $direction
 * @property int $amount
 * @property int $net_amount
 * @property int $balance_after
 * @property string $currency
 * @property string $description
 * @property array<string, mixed>|null $meta
 * @property string $idempotency_key
 * @property int|null $created_by_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LedgerEntry> $entries
 */
class LedgerTransaction extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['reference', 'tenant_id', 'source', 'source_reference', 'order_uuid', 'direction', 'amount', 'net_amount', 'balance_after', 'currency', 'description', 'meta', 'idempotency_key', 'created_by_id', 'occurred_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'source' => LedgerSource::class,
            'direction' => LedgerDirection::class,
            'amount' => 'integer',
            'net_amount' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
            'created_by_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderBy('id');
    }

    public function money(string $column = 'amount'): Money
    {
        return Money::ofMinor((int) $this->getAttribute($column), $this->currency);
    }
}
