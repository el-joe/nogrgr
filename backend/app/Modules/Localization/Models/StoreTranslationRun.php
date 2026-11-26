<?php

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Paid whole-store AI translation run of one bought language (central DB, tenant
 * scoped). Written by Localization when the purchase is paid / retried; progress and the
 * final status are written by the P10 Ai run.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $language_id
 * @property string $language_code
 * @property int|null $addon_purchase_id
 * @property TranslationRunStatus $status
 * @property int $progress
 * @property int $total_items
 * @property int $done_items
 * @property int $failed_items
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $provider AI provider of the last start (TranslationProvider value)
 * @property string|null $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreTranslationRun extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'store_translation_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'language_id', 'language_code', 'addon_purchase_id', 'status', 'progress', 'total_items',
        'done_items', 'failed_items', 'attempts', 'last_error', 'provider', 'model', 'prompt_tokens', 'completion_tokens',
        'queued_at', 'started_at', 'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TranslationRunStatus::class,
            'progress' => 'integer',
            'total_items' => 'integer',
            'done_items' => 'integer',
            'failed_items' => 'integer',
            'attempts' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
