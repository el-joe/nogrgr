<?php

namespace App\Modules\Ai\Models;

use App\Modules\Ai\Enums\AdminTranslationKind;
use App\Modules\Ai\Enums\AdminTranslationRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One admin AI translation run (central DB, platform-wide, ADM-091b / ADM-092a).
 *
 * @property int $id
 * @property AdminTranslationKind $kind
 * @property string $locale
 * @property array<string, mixed>|null $scope static keys: {file: id|null}
 * @property AdminTranslationRunStatus $status
 * @property int $progress
 * @property int $total_items
 * @property int $done_items
 * @property int $failed_items
 * @property array<string, array{total: int, done: int, failed: int}>|null $sections
 * @property int $attempts
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $admin_id
 * @property string|null $last_error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
class AdminTranslationRun extends Model
{
    use CentralConnection;

    protected $table = 'admin_translation_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind', 'locale', 'scope', 'status', 'progress', 'total_items', 'done_items', 'failed_items', 'sections',
        'attempts', 'prompt_tokens', 'completion_tokens', 'provider', 'model', 'admin_id', 'last_error', 'started_at', 'finished_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'queued'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AdminTranslationKind::class,
            'status' => AdminTranslationRunStatus::class,
            'scope' => 'array',
            'sections' => 'array',
            'progress' => 'integer',
            'total_items' => 'integer',
            'done_items' => 'integer',
            'failed_items' => 'integer',
            'attempts' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'admin_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function scopeFile(): ?string
    {
        $file = $this->scope['file'] ?? null;

        return is_string($file) && $file !== '' ? $file : null;
    }
}
