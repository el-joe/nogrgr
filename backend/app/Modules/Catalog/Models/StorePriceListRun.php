<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\PriceListRunStatus;
use App\Modules\Commerce\Enums\ProfitType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Queued markup apply run of the price list modal (tenant DB, VEN-010a): progress =
 * processed / total products.
 *
 * @property string $id
 * @property PriceListRunStatus $status
 * @property list<int> $product_ids
 * @property ProfitType $profit_type
 * @property string $profit_value
 * @property int|null $country_id
 * @property int|null $variant_id
 * @property int $total
 * @property int $processed
 * @property string|null $error
 * @property int|null $started_by
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StorePriceListRun extends Model
{
    use HasUuids;

    protected $table = 'store_price_list_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status', 'product_ids', 'profit_type', 'profit_value', 'country_id', 'variant_id',
        'total', 'processed', 'error', 'started_by', 'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PriceListRunStatus::class,
            'product_ids' => 'array',
            'profit_type' => ProfitType::class,
            'profit_value' => 'decimal:2',
            'country_id' => 'integer',
            'variant_id' => 'integer',
            'total' => 'integer',
            'processed' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function percent(): int
    {
        return $this->total > 0 ? (int) floor($this->processed / $this->total * 100) : ($this->status->isFinished() ? 100 : 0);
    }
}
