<?php

namespace App\Modules\Ai\Models;

use App\Modules\Ai\Enums\AiFeature;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Tokens of one AI call batch (central DB, tenant scoped; tenant_id null = platform).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property AiFeature $feature
 * @property string $provider TranslationProvider value
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property string|null $subject e.g. "run:12", "product:5"
 * @property Carbon|null $created_at
 */
class AiUsageLog extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'ai_usage_logs';

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'feature', 'provider', 'model', 'prompt_tokens', 'completion_tokens', 'subject'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'feature' => AiFeature::class,
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }
}
