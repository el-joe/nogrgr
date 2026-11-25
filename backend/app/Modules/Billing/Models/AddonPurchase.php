<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\AddonPurchaseFactory;
use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Platform\Concerns\Auditable;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A paid add-on a store bought (central DB, tenant scoped). Amount in USD minor units.
 *
 * @property int $id
 * @property int $tenant_id
 * @property AddonType $type
 * @property string $item_code
 * @property int $amount_minor
 * @property string $currency
 * @property AddonPurchaseStatus $status
 * @property int|null $payment_intent_id
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AddonPurchase extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<AddonPurchaseFactory> */
    use HasFactory;

    protected $table = 'addon_purchases';

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'type', 'item_code', 'amount_minor', 'currency', 'status', 'payment_intent_id', 'paid_at', 'meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'status' => AddonPurchaseStatus::class,
            'amount_minor' => 'integer',
            'payment_intent_id' => 'integer',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return MorphMany<Invoice, $this>
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }
}
