<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\PaymentLogFactory;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Platform\Concerns\Auditable;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Gateway payment attempt for a platform charge (central DB, tenant scoped). USD minor units.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $invoice_id
 * @property PaymentGateway $gateway
 * @property PaymentPurpose $purpose
 * @property PaymentLogStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $gateway_reference
 * @property array<string, mixed>|null $payload
 * @property string|null $note
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice|null $invoice
 */
class PaymentLog extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<PaymentLogFactory> */
    use HasFactory;

    protected $table = 'payment_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'invoice_id', 'gateway', 'purpose', 'status', 'amount_minor', 'currency',
        'gateway_reference', 'payload', 'note', 'paid_at', 'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'purpose' => PaymentPurpose::class,
            'status' => PaymentLogStatus::class,
            'amount_minor' => 'integer',
            'payload' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reference(): string
    {
        return 'PL-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }
}
