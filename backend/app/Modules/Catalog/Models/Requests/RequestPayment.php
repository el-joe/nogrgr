<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Catalog\Enums\PaymentRequestStatus;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Payment request on a manufacturing / brand request. Each one is billed through a Billing
 * invoice (billable = this row); the vendor pays the invoice, InvoicePaid marks it paid.
 *
 * @property int $id
 * @property string $requestable_type
 * @property int $requestable_id
 * @property int $tenant_id
 * @property string $label
 * @property Money $amount
 * @property string $currency
 * @property PaymentRequestStatus $status
 * @property int|null $invoice_id
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property-read Invoice|null $invoice
 */
class RequestPayment extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'catalog_request_payments';

    protected $guarded = ['id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'status' => PaymentRequestStatus::class,
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
