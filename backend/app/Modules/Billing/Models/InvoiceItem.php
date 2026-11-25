<?php

namespace App\Modules\Billing\Models;

use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Invoice line (central DB). total = quantity × unit, USD minor units.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_amount_minor
 * @property int $total_minor
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvoiceItem extends Model
{
    use CentralConnection;

    protected $table = 'invoice_items';

    /**
     * @var list<string>
     */
    protected $fillable = ['invoice_id', 'description', 'quantity', 'unit_amount_minor', 'total_minor', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'total_minor' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function unitAmount(string $currency = Money::BASE_CURRENCY): Money
    {
        return Money::ofMinor($this->unit_amount_minor, $currency);
    }

    public function total(string $currency = Money::BASE_CURRENCY): Money
    {
        return Money::ofMinor($this->total_minor, $currency);
    }
}
