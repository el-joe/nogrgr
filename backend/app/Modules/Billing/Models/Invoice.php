<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\InvoiceFactory;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Platform\Concerns\Auditable;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform invoice to a store (central DB, tenant scoped). USD minor units.
 *
 * @property int $id
 * @property string $number
 * @property int $tenant_id
 * @property InvoiceType $type
 * @property InvoiceStatus $status
 * @property string|null $billable_type
 * @property int|null $billable_id
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $tax_rate_bp
 * @property int $tax_minor
 * @property int $total_minor
 * @property string|null $bill_to_name
 * @property string|null $bill_to_email
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InvoiceItem> $items
 */
class Invoice extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $table = 'invoices';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number', 'tenant_id', 'type', 'status', 'billable_type', 'billable_id', 'currency', 'subtotal_minor',
        'tax_rate_bp', 'tax_minor', 'total_minor', 'bill_to_name', 'bill_to_email', 'payment_method',
        'payment_reference', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'subtotal_minor' => 'integer',
            'tax_rate_bp' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subtotal(): Money
    {
        return Money::ofMinor($this->subtotal_minor, $this->currency);
    }

    public function tax(): Money
    {
        return Money::ofMinor($this->tax_minor, $this->currency);
    }

    public function total(): Money
    {
        return Money::ofMinor($this->total_minor, $this->currency);
    }

    public function taxPercent(): float
    {
        return $this->tax_rate_bp / 100;
    }
}
