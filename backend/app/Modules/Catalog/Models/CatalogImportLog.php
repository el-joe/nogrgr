<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ImportLogResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One line of an import run log (central DB): the item, its outcome and a message.
 *
 * @property int $id
 * @property int $run_id
 * @property ImportLogResult $result
 * @property int|null $external_id
 * @property string|null $sku
 * @property string|null $item_name
 * @property int|null $product_id
 * @property string $message
 * @property Carbon|null $created_at
 */
class CatalogImportLog extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'catalog_import_logs';

    /**
     * @var list<string>
     */
    protected $fillable = ['run_id', 'result', 'external_id', 'sku', 'item_name', 'product_id', 'message'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => ImportLogResult::class,
            'external_id' => 'integer',
            'product_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogImportRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CatalogImportRun::class, 'run_id');
    }
}
