<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Repositories\Contracts\AiUsageReportRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads `ai_usage_logs` on the central connection (rows with tenant_id null = platform /
 * admin work). The store filter narrows to one store; the country filter does not apply.
 *
 * @extends BaseRepository<AiUsageLog>
 */
class AiUsageReportRepository extends BaseRepository implements AiUsageReportRepositoryContract
{
    protected function model(): string
    {
        return AiUsageLog::class;
    }

    public function totals(ReportFilterData $filters): array
    {
        $row = $this->logs($filters)
            ->selectRaw('COUNT(*) AS calls, COALESCE(SUM(prompt_tokens), 0) AS prompt, COALESCE(SUM(completion_tokens), 0) AS completion, COUNT(DISTINCT tenant_id) AS stores')
            ->first();

        return [
            'calls' => (int) ($row->calls ?? 0),
            'prompt' => (int) ($row->prompt ?? 0),
            'completion' => (int) ($row->completion ?? 0),
            'stores' => (int) ($row->stores ?? 0),
        ];
    }

    public function tokensByFeature(ReportFilterData $filters): array
    {
        return $this->logs($filters)
            ->selectRaw('feature, SUM(prompt_tokens + completion_tokens) AS tokens')
            ->groupBy('feature')
            ->pluck('tokens', 'feature')
            ->map(static fn (mixed $tokens): int => (int) $tokens)
            ->all();
    }

    public function grouped(ReportFilterData $filters): Builder
    {
        $grouped = $this->logs($filters)
            ->selectRaw('tenant_id, feature, provider, model, COUNT(*) AS calls, SUM(prompt_tokens) AS prompt_tokens, SUM(completion_tokens) AS completion_tokens, SUM(prompt_tokens + completion_tokens) AS total_tokens, MAX(created_at) AS last_used_at')
            ->groupBy('tenant_id', 'feature', 'provider', 'model');

        return $this->db()->query()->fromSub($grouped, 'usage')
            ->leftJoin('tenants', 'tenants.id', '=', 'usage.tenant_id')
            ->select('usage.*', 'tenants.name AS tenant_name', 'tenants.slug AS tenant_slug');
    }

    private function logs(ReportFilterData $filters): Builder
    {
        return $this->db()->table('ai_usage_logs')
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $filters->tenantId));
    }

    private function db(): ConnectionInterface
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
