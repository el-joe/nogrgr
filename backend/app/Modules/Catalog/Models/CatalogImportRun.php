<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Identity\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One external catalog import run (central DB) with its live counters.
 *
 * @property int $id
 * @property ImportDriver $driver
 * @property ImportStatus $status
 * @property array{max_pages?: int|null, update_existing?: bool}|null $options
 * @property int|null $total_pages
 * @property int $pages_done
 * @property int $total_items
 * @property int $processed_count
 * @property int $created_count
 * @property int $updated_count
 * @property int $skipped_count
 * @property int $error_count
 * @property int $images_total
 * @property int $images_done
 * @property string|null $batch_id
 * @property int|null $started_by
 * @property string|null $failure_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Admin|null $starter
 */
class CatalogImportRun extends Model
{
    use CentralConnection;

    protected $table = 'catalog_import_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver', 'status', 'options', 'total_pages', 'pages_done', 'total_items', 'processed_count',
        'created_count', 'updated_count', 'skipped_count', 'error_count', 'images_total', 'images_done',
        'batch_id', 'started_by', 'failure_message', 'started_at', 'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => ImportDriver::class,
            'status' => ImportStatus::class,
            'options' => 'array',
            'total_pages' => 'integer',
            'pages_done' => 'integer',
            'total_items' => 'integer',
            'processed_count' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'error_count' => 'integer',
            'images_total' => 'integer',
            'images_done' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'started_by');
    }

    /**
     * @return HasMany<CatalogImportLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(CatalogImportLog::class, 'run_id');
    }

    public function updatesExisting(): bool
    {
        return (bool) ($this->options['update_existing'] ?? true);
    }

    public function maxPages(): ?int
    {
        $max = $this->options['max_pages'] ?? null;

        return is_numeric($max) && (int) $max > 0 ? (int) $max : null;
    }

    public function percent(int $done, int $total): int
    {
        return $total > 0 ? (int) min(100, floor($done * 100 / $total)) : ($this->status === ImportStatus::Completed ? 100 : 0);
    }
}
