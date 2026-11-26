<?php

namespace App\Modules\Ai\Repositories\Eloquent;

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<AiUsageLog>
 */
class AiUsageLogRepository extends BaseRepository implements AiUsageLogRepositoryContract
{
    protected function model(): string
    {
        return AiUsageLog::class;
    }

    public function productTranslationRows(int $tenantId): QueryBuilder
    {
        return $this->productTranslations($tenantId)
            ->select([
                DB::raw("'product' as kind"), 'id', 'tenant_id', DB::raw('null as language_code'), DB::raw('null as `trigger`'),
                DB::raw("'completed' as status"), DB::raw('null as done_items'), DB::raw('null as failed_items'),
                DB::raw('(prompt_tokens + completion_tokens) as tokens'), DB::raw('created_at as started_at'), DB::raw('created_at as finished_at'),
                'subject', DB::raw('created_at as sort_at'),
            ]);
    }

    public function productTranslationStats(int $tenantId): array
    {
        $row = $this->productTranslations($tenantId)
            ->selectRaw('count(*) as calls')
            ->selectRaw('coalesce(sum(case when created_at >= ? then 1 else 0 end), 0) as this_month', [now()->startOfMonth()])
            ->selectRaw('coalesce(sum(prompt_tokens + completion_tokens), 0) as tokens')
            ->first();

        return ['calls' => (int) ($row->calls ?? 0), 'this_month' => (int) ($row->this_month ?? 0), 'tokens' => (int) ($row->tokens ?? 0)];
    }

    private function productTranslations(int $tenantId): QueryBuilder
    {
        return $this->query()->withoutGlobalScopes()->toBase()
            ->where('tenant_id', $tenantId)
            ->where('feature', AiFeature::ProductTranslation->value);
    }
}
